<?php

namespace FWAPI;

use FWCommon\Mail;
use GFAPI;
use DateTimeZone;
use DateTime;
use WP_Error;

class Jobs {
	private static $form_id = 158;
	private static $data = null;
	private static $entry = [
		'form_id'  => 158,
		'input_87' => 'reseller',
		'input_79' => 'Ja',
	];
	private static $cams_api_url;
	private static $form;
	private static $transform = [
		'job_type'                           => [
			'id'       => 137,
			'callback' => 'format_jobtype',
		],
		'title'                              => [
			'id'     => 65,
			'update' => [
				'field' => 'post_title',
			],
		],
		'organisation'                       => [
			'id' => 9,
			'update' => [
				'field' => 'meta__vac_org_naam',
			]
		],
		'address'                            => [
			'street_address' => [
				'id' => 138,
			],
			'zipcode'        => [
				'id' => 139,
			],
			'city'           => [
				'id' => 11,
			],
			'country'        => [
				'id'       => 140,
				'callback' => 'format_country'
			],
			'update' => [
				'callback' => 'update_address',
			]
		],
		'job_text'                           => [
			'id'     => 6,
			'update' => [
				'field' => 'post_content',
			],
		],
		'job_link'                           => [
			'id' => 14,
			'update' => [
				'field' => 'meta__job_link',
			]
		],
		'job_language' => [
			'id' => 146,
			'callback' => 'format_job_language',
			'update' => [
				'field' => 'vac_taal',
			]
		],
		'minimum_salary' => [
			'id' => 149,
			'update' => [
				'field' => 'meta__vac_salaris_min'
			]
		],
		'maximum_salary' => [
			'id' => 148,
			'update' => [
				'field' => 'meta__vac_salaris_max'
			]
		],
		'level'                              => [
			'id'       => 126,
			'callback' => 'format_level',
			'update'   => [
				'field' => 'job_skill_level',
			],
		],
		'date' => [
			'id' => 134,
			'callback' => 'format_date',
		],
		'expire_timestamp'                   => [
			'id'       => 116,
			'callback' => 'format_date',
		],
		'specializations'                    => [
			'id'       => 123,
			'callback' => 'format_specializations',
		],
		'discipline'                         => [
			'id'       => 143,
			'callback' => 'format_disciplines',
			'update' => [
				'callback' => 'update_discipline',
			]
		],
		'employment'                         => [
			'id'       => 124,
			'callback' => 'format_employment',
			'update' => [
				'callback' => 'update_employment',
			]
		],
		'hours_per_week'                     => [
			'id'     => 37,
			'update' => [
				'field' => 'meta__vac_uur',
			],
		],
		'company_logo_supplied'              => [
			'id'       => 117,
			'callback' => 'format_company_logo_supplied',
		],
		'company_logo_path'                  => [
			'id'   => 18,
			'type' => 'file',
		],
		'use_personalised_header'            => [
			'id'       => 129,
			'callback' => 'format_personalised_header',
		],
		'personalised_header_path'           => [
			'id'   => 130,
			'type' => 'file',
		],
		'social_media_image'                 => [
			'id'   => 132,
			'type' => 'file',
		],
		'apply_method'                       => [
			'id'       => 94,
			'callback' => 'format_apply_method',
			'update' => [
				'field' => 'meta__solliciteren_via',
			]
		],
		'apply_website'                      => [
			'id' => 25,
			'update' => [
				'field' => 'meta__vac_org_collpagina',
			]
		],
		'apply_email'                        => [
			'id' => 26,
			'update' => [
				'field' => 'meta__vac_org_email_dirsoll',
			]
		],
		'contact_person_for_candidate'       => [
			'id'     => 21,
			'update' => [
				'field' => 'meta__vac_org_contactpers',
			],
		],
		'contact_person_phone_for_candidate' => [
			'id'     => 23,
			'update' => [
				'field' => 'meta__vac_org_tel',
			],
		],
		'contact_person_email_for_candidate' => [
			'id'     => 66,
			'update' => [
				'field' => 'meta__vac_contact_email',
			],
		],
		'reseller_contact_first_name'        => [
			'id' => 99,
		],
		'reseller_contact_last_name'         => [
			'id' => 100,
		],
		'reseller_contact_phone_number'      => [
			'id' => 97,
		],
		'reseller_contact_email_address'     => [
			'id' => 42,
		],
		'reseller_organisation'              => [
			'id' => 44,
		]
	];

	public function __construct() {
		self::$cams_api_url = getenv( 'CAMS_API_URL' );

		add_action( 'fw_jobs_process_files', [ $this, 'action__fw_jobs_process_files' ] );
		add_action( 'gform_after_submission', [ $this, 'action__gform_after_submission' ], 10, 2 );
		add_action( 'transition_post_status', [ $this, 'action__transition_post_status' ], 10, 3 );
	}

	public function action__transition_post_status( $new_status, $old_status, $post ) {
		remove_action( 'transition_post_status', [ $this, 'action__transition_post_status' ], 10 );

		if ( 'vacature' === get_post_type( $post->ID ) && '' !== ( $external_job_id = get_post_meta( $post->ID, 'external_job_id', true ) ) ) {
			fwc()->cams->update_post( 'jobs/status/' . $external_job_id, [
				'status' => $new_status
			] );
		}

		add_action( 'transition_post_status', [ $this, 'action__transition_post_status' ], 10, 3 );
	}

	public function action__gform_after_submission( $entry, $form ) {

		if ( null !== self::$data && isset( $entry['post_id'] ) ) {
			remove_action( 'post_updated', 'wp_save_post_revision' );
			update_field( 'product_type', 7, $entry['post_id'] );
			update_post_meta( $entry['post_id'], 'external_job_id', self::$data['jobId'] );
			update_post_meta( $entry['post_id'], 'user_id', self::$data['userId'] );

			$job_expire_date = new DateTime( date( 'Y-m-d H:i:s', self::$data['expire_timestamp'] ), new DateTimeZone( 'UTC' ) );
			$job_expire_date->setTimezone( new DateTimeZone( 'Europe/Amsterdam' ) );
			$job_expire_timestamp = strtotime( $job_expire_date->format( 'Y-m-d H:i:s' ) );
			$categories           = [];

			foreach ( self::$data['discipline'] as $discipline ) {
				$args  = array(
					'hide_empty' => false, // also retrieve terms which are not used yet
					'meta_query' => array(
						array(
							'key'     => 'vacature_category',
							'value'   => $discipline,
							'compare' => 'LIKE'
						)
					),
					'taxonomy'   => 'category',
				);
				$terms = get_terms( $args );

				if ( ! empty( $terms ) ) {
					$categories[] = $terms[0]->term_id;
				}
			}

			wp_set_post_categories( $entry['post_id'], $categories );
			update_post_meta( $entry['post_id'], 'posts_expire', $job_expire_timestamp );


			if ( isset( self::$data['personalised_header_path'] ) ) {
				$image_id = media_sideload_image( self::format_uri( self::$data['personalised_header_path'] ), $entry['post_id'], null, 'id' );

				if ( ! is_wp_error( $image_id ) ) {
					update_field( 'header_image', $image_id, $entry['post_id'] );
				}
			}

			if ( isset( self::$data['company_logo_path'] ) ) {
				$image_id = media_sideload_image( self::format_uri( self::$data['company_logo_path'] ), $entry['post_id'], null, 'id' );

				if ( ! is_wp_error( $image_id ) ) {
					set_post_thumbnail( $entry['post_id'], $image_id );
				}
			}

			if ( isset( self::$data['social_media_image_path'] ) ) {
				$image_url = media_sideload_image( self::format_uri( self::$data['social_media_image_path'] ), $entry['post_id'], null, 'src' );

				if ( ! is_wp_error( $image_id ) ) {
					update_post_meta( $entry['post_id'], '_yoast_wpseo_opengraph-image', $image_url );
				}
			}

			if ( 'vacature' === self::$data['job_type'] ) {
				update_field( 'vacature_type', 28084, $entry['post_id'] );
			} else {
				update_field( 'vacature_type', 28085, $entry['post_id'] );
			}

			add_action( 'post_updated', 'wp_save_post_revision' );

			$entry_url = get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=gf_entries&view=entry&id=' . rgar( $form, 'id' ) . '&lid=' . rgar( $entry, 'id' );

			$job_edit_link = admin_url( 'post.php?post=' .  $entry['post_id'] . '&action=edit' );

			$content = <<<EOT
Hi team Jobs,<br /><br />

Er is zojuist een nieuwe vacature geplaatst via de API. Klik <a href="$job_edit_link">hier</a> om de vacature te bekijken in de admin. Werkt de link niet, kopieer dan deze URL<br />
$job_edit_link<br /><br />

De originele inzending kan je <a href="$entry_url">hier</a> vinden. Werkt de link niet, kopieer dan deze URL<br />
$entry_url<br /><br />

Groetjes. 
EOT;

			Mail::send( 'jobs@frankwatching.com', 'Nieuwe vacature via de API', $content, 'Frankwatching', 'job_mail' );
			//			var_dump( self::$data, $entry['post_id'] );
			exit;
		}
	}

	public static function format_uri( $image_path ) {
		return self::$cams_api_url . 'jobs' . $image_path;
	}

	public function action__fw_jobs_process_files( $fields ) {

		foreach ( $fields as $key => $field ) {
			if ( array_key_exists( $key, self::$transform ) && isset( self::$transform[ $key ]['type'] ) && 'file' === self::$transform[ $key ]['type'] ) {
				$file_path = self::format_uri( $field );

				$file_contents = @file_get_contents( $file_path );

				if ( false === $file_contents ) {
					continue;
				}

				$basename = pathinfo( $file_path, PATHINFO_BASENAME );

				$mimetype      = wp_check_filetype( $basename );
				$file_tmp_path = trailingslashit( sys_get_temp_dir() ) . $basename;

				$handle = fopen( $file_tmp_path, 'w' );
				fwrite( $handle, $file_contents );
				fclose( $handle );

				$_FILES[ 'input_' . self::$transform[ $key ]['id'] ] = [
					'name'     => $basename,
					'type'     => $mimetype['type'],
					'tmp_name' => $file_tmp_path,
					'error'    => UPLOAD_ERR_OK,
					'size'     => filesize( $file_tmp_path )
				];
			}
		}

		if ( ! isset( $_FILES['input_132'] ) ) {
			$_FILES['input_132'] = [
				'name'  => '',
				'size'  => 0,
				'error' => UPLOAD_ERR_NO_FILE
			];
		}
	}

	public static function create( \WP_REST_Request $request ) {

		self::$data = $request->get_params();
		$fields     = $request->get_params();
		do_action( 'fw_jobs_process_files', $fields );
		self::$form = GFAPI::get_form( self::$form_id );

		self::format_entry( $fields );
		self::$entry['input_134'] = date_i18n( 'd/m/Y' );
		
		GFAPI::submit_form( self::$form_id, self::$entry, [], 0, 0 );

		return [
			'success' => true
		];
	}

	public static function update( \WP_REST_Request $request ) {
		global $wpdb;
		$data   = $request->get_params();
		$update = [];

		$jobs = get_posts( [
			'post_type'   => 'vacature',
			'post_status' => [
				'pending',
				'publish',
			],
			'orderby'     => 'post_date',
			'order'       => 'ASC',
			'meta_query'  => [
				[
					'key'   => 'external_job_id',
					'value' => $data['jobId']
				]
			]
		] );

		$job          = reset( $jobs );
		$update['ID'] = $job->ID;


		$company = $data['organisation'];
		$city = $data['address']['city'];

		foreach ( $data as $key => $value ) {
			if ( 'title' === $key ) {
				$update['post_title'] = $value . ' bij ' . $company . ' in ' . $city;
				update_field( 'vac_functie', $value, $job->ID );
			} elseif ( 'level' === $key ) {
				delete_post_meta( $job->ID, 'job_skill_level' );

				foreach ( $value as $level ) {
					add_post_meta( $job->ID, 'job_skill_level', ucfirst( $level ) );
				}

				$value = array_map( 'self::format_vac_niveau', $value );

				update_field( 'vac_niveau', $value, $job->ID );
			} elseif ( array_key_exists( $key, self::$transform ) && array_key_exists( 'update', self::$transform[ $key ] ) ) {
				if ( isset( self::$transform[ $key ]['update']['field'] ) && 'meta__' === substr( self::$transform[ $key ]['update']['field'], 0, 6 ) ) {
					$meta_field = substr( self::$transform[ $key ]['update']['field'], 6 );

					update_field( $meta_field, $value, $job->ID );
				} elseif( isset( self::$transform[ $key ]['update']['callback'] ) ) {
					call_user_func( [ __CLASS__, self::$transform[ $key ]['update']['callback'] ], $value, $job );
				} else {
					$update[ self::$transform[ $key ]['update']['field'] ] = $value;
				}
			}
		}

		$result = wp_update_post(
			$update
		);

//		if ( ! is_wp_error( $result ) && 'pending' !== $job->post_status ) {
		$job_edit_link = admin_url( 'post.php?post=' . $job->ID . '&action=edit' );

		$content = <<<EOT
Hi team Jobs,<br /><br />

De vacature $job->post_title is zojuist gewijzigd via de API. Ga naar <a href="$job_edit_link">$job_edit_link</a> om de wijzigingen te bekijken.<br/><br /> Werkt de link niet, kopieer dan deze URL <br />$job_edit_link. <br /><br />

Groetjes. 
EOT;


		Mail::send( 'jobs@frankwatching.com', 'Gewijzigde vacature via de API', $content, 'Frankwatching', 'job_mail' );

//		}

		return [
			'success' => true,
		];
	}

	public static function set_categories( $value, $id ) {
		$terms = get_terms( [
			'taxonomy'   => 'category',
			'meta_query' => [
				'relation' => 'AND',
				[
					'key'     => 'vacature_category',
					'compare' => 'EXISTS'
				],
				[
					'key'     => 'vacature_category',
					'value'   => [ '' ],
					'compare' => 'NOT IN'
				],
			]
		] );

		$categories = [];

		foreach ( $terms as $term ) {
			$term->job_term_name = get_term_meta( $term->term_id, 'vacature_category', true );
		}

		$job_term_names = wp_list_pluck( $terms, 'job_term_name' );

		foreach ( $value as $val ) {
			$key = array_search( $val, $job_term_names );

			$categories[] = $terms[ $key ]->term_id;
		}

		wp_set_object_terms( $id, $categories, 'category' );
	}

	public static function update_acf_field( $value, $field, $id ) {
		update_field( $field, $value, $id );
	}

	public static function format_vac_niveau( $value ) {
		$term = get_term_by( 'slug', $value, 'job_skill_level' );

		return $term->term_id;
	}

	public static function update_address( $value, $job ) {
		$formatted = self::format_address( $value );

		update_post_meta( $job->ID, 'vac_standplaats', $value['city'] );
		update_post_meta( $job->ID, 'google_map', $formatted );
	}

	public static function format_address( $value ) {
		$address = $value['street_address'] . ',' . $value['zipcode'] . ',' . $value['city'] . ',' . $value['country'];

		$request_url = add_query_arg( [
			'address' => $address,
			'key'     => getenv( 'GOOGLE_MAPS_API_KEY' ),
		], 'https://maps.googleapis.com/maps/api/geocode/json' );

		$results = wp_remote_get( $request_url );

		if ( ! is_wp_error( $results ) ) {
			$body = wp_remote_retrieve_body( $results );

			$geocode = json_decode( $body );

			if ( isset( $geocode->results[0]->geometry->location ) ) {
				return [
					'address' => $address,
					'lat'     => $geocode->results[0]->geometry->location->lat,
					'lng'     => $geocode->results[0]->geometry->location->lng,
				];
			}
		}

		return [];

	}

	public static function update__post_title( $value, $post, $data ) {
		$post['post_title'] = $value . ' bij ' . $data['organisation'] . ' in ' . $data['address']['city'];

		return $post;
	}


	private static function update__address( $value, $job_meta, $data ) {


	}

	public static function update__vacature_type( $value, $job_meta, $data ) {
		return $job_meta;
	}

	public static function update__employment( $value, $job_meta, $data ) {
		return $job_meta;
	}

	public static function update__job_level( $value, $job_meta, $data ) {
		return $job_meta;
	}

	public static function update_post_title( $value, $post_args, $post ) {
//		$post_args['post_title'] = $value ' bij '
	}


	public static function delete( \WP_REST_Request $request ) {
		// Get the post belonging to the external ID
		//		var_dump( strtotime( '-1 day' ) );
		//		exit;

		$posts = get_posts( [
			'post_status' => 'all',
			'post_type'   => 'vacature',
			'meta_query'  => [
				[
					'key'   => 'external_job_id',
					'value' => $request->get_param( 'jobId' ),
				]
			]
		] );

		$user_id = $request->get_param( 'userId' );
		$user = get_user_by( 'id', $user_id );
		$post = reset( $posts );

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return new WP_Error( 'nothing_found', 'Invalid Job', array( 'status' => 404 ) );
		}

		update_post_meta( $post->ID, 'posts_expire', strtotime( '-1 day' ) );
		ep_delete_post( $post->ID );

		$edit_post_link = 'https://www.frankwatching.com/wp/wp-admin/post.php?post=' . $post->ID . '&action=edit';



		$body = "Hi Team Jobs,\n\n
			De vacature \"$post->post_title\" is zojuist offline gehaald. Klik <a href=\"$edit_post_link\">hier</a> om de vacature te bekijken in het systeem.\n\n
			Groetjes Jobbie
		";

		wp_mail(
			'jobs@frankwatching.com',
			'Vacature offline gehaald',
			nl2br( $body ),
			[
				'Content-Type: text/html; charset=UTF-8'
			]
		);
		
		$sender_body = "Hi,
		
			Bedankt. We hebben je verzoek om de vacature offline te halen ontvangen en halen de vacature van onze website. Mocht je verder nog vragen hebben, neem dan vooral even contact op.
			
			Hartelijke groet,
			Team Frankwatching Jobs";

		Mail::send( $user->user_email, 'Vacature offline gehaald', nl2br( $sender_body ), 'Frankwatching Jobs' );

		return [
			'success' => true,
		];

	}

	public static function format_entry( $fields ) {
		//		self::$entry['form_id'] = self::$form_id;

		foreach ( $fields as $key => $value ) {
			if ( array_key_exists( $key, self::$transform ) && ( ! isset( self::$transform[ $key ]['type'] ) || 'file' !== self::$transform[ $key ]['type'] ) ) {
				if ( is_array( $value ) ) {
					foreach ( $value as $k => $v ) {
						if ( array_key_exists( $k, self::$transform[ $key ] ) ) {
							self::format_value( self::$transform[ $key ][ $k ], $v );
						} else {
							self::format_value( self::$transform[ $key ], $value );
							break;
						}
					}
				} else {
					self::format_value( self::$transform[ $key ], $value );
				}
			}
		}
	}

	private static function format_value( $transform_field, $value ) {
		if ( isset( $transform_field['callback'] ) ) {
			$form_value = call_user_func( [ __CLASS__, $transform_field['callback'] ], $value, $transform_field );

			if ( is_array( $form_value ) ) {
				foreach ( $form_value as $item ) {
					self::$entry[ 'input_' . str_replace( '.', '_', $item['key'] ) ] = $item['value'];
				}

				return;
			}

			self::$entry[ 'input_' . $transform_field['id'] ] = $form_value;

			return;
		}

		self::$entry[ 'input_' . $transform_field['id'] ] = $value;
	}

	private static function format_specializations( $value ) {
		return implode( ',', $value );
	}

	private static function format_date( $value ) {
		return date( 'Y-m-d', $value );
	}

	private static function format_country( $value ) {
		$countries = file_get_contents( __DIR__ . '/json/countries.json' );

		$countries = json_decode( $countries, true );

		$country = array_filter( $countries, function( $country ) use ( $value ) {
			return $country['short_name'] === $value;
		} );

		$country = reset( $country );

		return $country['name'];
	}

	public static function update_discipline( $value, $job ) {
		foreach ( $value as $discipline ) {
			$args  = array(
				'hide_empty' => false, // also retrieve terms which are not used yet
				'meta_query' => array(
					array(
						'key'     => 'vacature_category',
						'value'   => $discipline,
						'compare' => 'LIKE'
					)
				),
				'taxonomy'   => 'category',
			);

			$terms = get_terms( $args );

			if ( ! empty( $terms ) ) {
				$categories[] = $terms[0]->term_id;
			}
		}

		wp_set_post_categories( $job->ID, $categories );
	}

	public static function update_employment( $value, $job ) {
		$employment_formatted = self::update__format_employment( $value );

		update_post_meta( $job->ID, 'vac_full_part', $employment_formatted );
	}

	private static function format_disciplines( $value, $transform_field ) {
		// Find the field in the form.
		$options = [];

		$form_field = self::get_form_field( $transform_field );

		foreach ( $value as $discipline ) {
			$input = array_filter( $form_field['inputs'], function( $input ) use ( $discipline ) {
				return $input['label'] === $discipline;
			} );

			$input = reset( $input );

			$options[] = [
				'key'   => $input['id'],
				'value' => $input['label']
			];
		}

		return $options;
	}

	private static function update__format_employment( $value ) {
		$retval = [];
		foreach ( $value as $val ) {
			$term = get_term_by( 'slug', strtolower( $val ), 'job_contract_type' );

			if ( ! is_wp_error( $term ) ) {
				$retval[] = $term->term_id;
			}
		}

		return $retval;
	}

	private static function format_employment( $value, $transform_field ) {
		$options    = [];
		$form_field = self::get_form_field( $transform_field );

		foreach ( $value as $employment ) {
			$field = array_filter( $form_field['inputs'], function( $input ) use ( $employment ) {
				return $input['label'] === $employment;
			} );

			if ( ! empty( $field ) ) {
				$field    = reset( $field );
				$field_id = $field['id'];

				$field_choice = array_filter( $form_field['choices'], function( $choice ) use ( $employment ) {
					return $choice['text'] === $employment;
				} );

				if ( ! empty( $field_choice ) ) {
					$field_choice = reset( $field_choice );

					$options[] = [
						'key'   => $field_id,
						'value' => $field_choice['value']
					];
				}
			}
		}

		return $options;
	}

	private static function format_job_language( $value ) {
		$options    = [];
		$form_field = self::get_form_field( self::$transform['job_language'] );

		foreach ( $value as $language ) {
			if ( 'dutch' === $language ) {
				$slug = 'nederlands';
			} elseif ( 'english' === $language ) {
				$slug = 'engels';
			}

			$field = array_filter( $form_field['inputs'], function( $input ) use ( $slug ) {
				return strtolower( $input['label'] ) === $slug;
			} );

			if ( ! empty( $field ) ) {
				$field     = reset( $field );
				$options[] = [
					'key'   => $field['id'],
					'value' => $field['label']
				];
			}
		}
		
		return $options;
	}

	private static function format_level( $levels, $transform_field ) {
		$options    = [];
		$form_field = self::get_form_field( $transform_field );

		foreach ( $levels as $level ) {
			$field = array_filter( $form_field['inputs'], function( $input ) use ( $level ) {
				return strtolower( $input['label'] ) === $level;
			} );

			if ( ! empty( $field ) ) {
				$field     = reset( $field );
				$options[] = [
					'key'   => $field['id'],
					'value' => $field['label']
				];
			}
		}

		return $options;
	}

	private static function format_company_logo_supplied( $value ) {
		if ( true === $value ) {
			$value = 'Ja';
		} else {
			$value = 'Nee';
		}

		return $value;
	}

	private static function format_personalised_header( $value ) {
		switch ( $value ) {
			case 'yes' :
				$value = 'Ja, ik wil graag een header aanleveren';
				break;
			case 'no' :
				$value = 'Nee, ik maak gebruik van een standaard header';
				break;
			case 'supplied' :
				$value = 'Ja, maar deze heb ik al eerder aangeleverd';
				break;
		}

		return $value;
	}

	private static function format_jobtype( $value ) {
		switch ( $value ) {
			case 'vacature' :
				$value = 'reseller';
				break;
		}

		return $value;
	}

	private static function format_apply_method( $value ) {
		switch ( $value ) {
			case 'email' :
				$value = 'E-mail';
				break;
			case 'website' :
				$value = 'Formulier';
				break;
		}

		return $value;
	}

	private static function get_form_field( $transform_field ) {
		$form_field = array_filter( self::$form['fields'], function( $field ) use ( $transform_field ) {
			return $field['id'] === $transform_field['id'];
		} );

		return reset( $form_field );
	}
}

new Jobs();