<?php
/**
 * @package CC KISSmetrics
 */
/*
Plugin Name: CC KISSmetrics
Description: A Community Commons-specific implementation of KISS Metrics.
Version: 0.1
Author: James Cutts, Mike Barbaro, Dave Cavins
Author URI: http://www.communitycommons.org
*/

define('CC_KISSMETRICS_VERSION', '0.1');
define('CC_KISSMETRICS_PLUGIN_URL', plugin_dir_url( __FILE__ ));

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo "Hi there! I'm just a plugin, not much I can do when called directly.";
	exit;
}

$km_key = '';

if ( is_admin() )
	require_once dirname( __FILE__ ) . '/admin.php';

if( !class_exists( 'KM_Filter' ) ) {
	class KM_Filter {
		static $link_regex = '/<a (.*?)href="(.*?)"(.*?)>(.*?)<\/a>/i';

		/**
		 * Outputs the KISSmetrics analytics script block.
		 */
		function output_analytics() {
			global $km_key;

			// As long as the API key is set, output the analytics. Also add code to catch if the user is viewing the homepage.
			if( $km_key != '' ) {
			?><script type="text/javascript">
			  var _kmq = _kmq || [];
			  var _kmk = _kmk || '<?php echo $km_key; ?>';
			  function _kms(u){
			    setTimeout(function(){
			    var s = document.createElement('script'); var f = document.getElementsByTagName('script')[0]; s.type = 'text/javascript'; s.async = true;
			    s.src = u; f.parentNode.insertBefore(s, f);
			    }, 1);
			  }
			  _kms('//i.kissmetrics.com/i.js');_kms('//doug1izaerwt3.cloudfront.net/' + _kmk + '.1.js');
			  _kmq.push(function() {
			    if(document.getElementsByTagName('body')[0].className.match('home')) {
			    	_kmq.push(['record', 'Viewed Blog Homepage']);
			    }
			  });

			<?php
				// Identify authenticated users
				if( is_user_logged_in() ) {
					global $current_user;
					get_currentuserinfo();
					?>_kmq.push(['identify', '<?php echo $current_user->user_email ?>']);
					<?php
				}

				// Track social button interactions (tweet, like/unlike, FB connect)
				if( get_option( 'cc_kissmetrics_track_social' ) ) {
					?>_kmq.push(function() {
						if(window.twttr) {
						  window.twttr.events.bind('tweet', function (event) {
						    var url = KM.uprts(decodeURIComponent(event.target.src).replace('#', '?')).params.url;
						    _kmq.push(['record', 'Tweet', { 'Shared URL': url }]);
						  });

						  window.twttr.events.bind('follow', function (event) {
						  	_kmq.push(['record', 'Twitter Follow', { 'Username': event.data.screen_name }]);
						  });
						}

						if(window.FB) {
						  window.FB.Event.subscribe('edge.create', function (url) {
						    _kmq.push(['record', 'Like', { 'Shared URL': url }]);
						  });

						  window.FB.Event.subscribe('edge.remove', function (url) {
						    _kmq.push(['record', 'Unlike', { 'Shared URL': url }]);
						  });

						  window.FB.Event.subscribe('auth.login', function (url) {
						    _kmq.push(['record', 'Facebook Connect\'d']);
						  });

						  window.FB.Event.subscribe('auth.logout', function (url) {
						    _kmq.push(['record', 'Facebook Logout']);
						  });
						}
					});
					<?php
				}
				// Track search queries
				if( get_option( 'cc_kissmetrics_track_search' ) ) {
					?>_kmq.push(function() {
						if(document.getElementsByTagName('body')[0].className.match('search-results')) {
							try {
								var query = KM.uprts(decodeURIComponent(window.location.href)).params.s;
								_kmq.push(['record', 'Searched Site', {'WordPress Search Query': query}]);
							} catch(e) {}
						}
					});
				<?php
				}
				// Page-specific click listeners:
				// Grant Support: http://www.communitycommons.org/chi-planning/
				if( is_page( 28588 ) ) {
					?>_kmq.push(['trackClick', '.why-use-this-tool', 'Clicked Target Area Intervention Tool planning guide']);
					_kmq.push(['trackClick', '.how-to-use-this-tool', 'Clicked Target Area Intervention Tool step-by-step guide']);
					_kmq.push(['trackClick', '.access-target-intervention-area-tool', 'Clicked Target Area Intervention Tool']);
					_kmq.push(['trackClick', '.target-intervention-area-tutorial', 'Clicked Target Area Intervention Tool tutorial videos']);
				<?php
				}

				// Taxonomy pages
				// Channel (category) pages
				if ( is_category() ) {
					$cat = array(
						'record',
						'Viewed Category',
						array(
							'Viewed category archive' => single_cat_title( '', false ),
						)
					);
					?>_kmq.push(<?php echo json_encode( $cat ); ?>);
					<?php
				}
				// Tag pages
				if ( is_tag() ) {
					$tag = array(
						'record',
						'Viewed Tag',
						array(
							'Viewed tag archive' => single_tag_title( '', false ),
						)
					);
					?>_kmq.push(<?php echo json_encode( $tag ); ?>);
					<?php
				}

				// Other taxonomies
				if ( is_tax() ) {
					global $wp_query;
					if ( isset( $wp_query->query_vars['term'] ) ) {
						$tax_term = get_term_by( 'slug', $wp_query->query_vars['term'], $wp_query->query_vars['taxonomy'] );
					}
					if ( $tax_term ) {
						$action = 'Viewed ' . $wp_query->query_vars['taxonomy'] . ' taxonomy term';
						$property_key = 'Viewed ' . $wp_query->query_vars['taxonomy'] . ' taxonomy term archive';
						$tax_item = array(
							'record',
							$action,
							array(
								$property_key => $tax_term->name,
							)
						);

						?>_kmq.push(<?php echo json_encode( $tax_item ); ?>);
						<?php
					}
				} 

				?></script>
				<?php
			}

		}


		/**
		 * Based on the provided host/uri string, returns the domain and the host in a hash.
		 *
		 * @param string $uri The URI/host string.
		 * @return object The associative array with two keys: "domain" (e.g., google.com) and "host" (e.g., mail.google.com)
		 */
		function get_domain( $uri ) {
			$parsed_uri = parse_url( $uri );
			if( isset( $parsed_uri['host'] ) )
				$host = $parsed_uri['host'];
			else
				$host = '';

			preg_match( '/[^\.\/]+\.[^\.\/]+$/', $host, $domain );

			if( !count( $domain ) )
				$domain = array( '' );

			return array( 'domain' => $domain[0], 'host' => $host );
		}

		/*********************************************
		 * Begin php-event-based tracking submissions.
		 */

		/**
		 * Track when a user registers (BP-safe).
		 */
		public function track_registration_bp( $user_id ) {
			include_once('km.php');
			$user = get_user_by( 'id', $user_id );

			KM::init( get_option( 'cc_kissmetrics_key' ) );
			KM::identify( $user->user_email );
			KM::record( 'Created account / registered' );
		}

		/**
		 * Track when a user leaves the site.
		 */
		public function track_user_account_delete( $user_id ) {
			include_once('km.php');
			$user = get_user_by( 'id', $user_id );

			KM::init( get_option( 'cc_kissmetrics_key' ) );
			KM::identify( $user->user_email );
			KM::record( 'Deleted account' );
		}
		/**
		 * Track when a user logs in.
		 */
		public function track_cc_login( $user_login, $user ) {
		    include_once('km.php');

			KM::init( get_option( 'cc_kissmetrics_key' ) );
			KM::identify( $user->user_email );
			KM::record( 'Logged in' );
		}

		/**
		 * Track when a user joins a group.
		 */
		public function track_join_bp_group( $group_id, $user_id, $reason = null ) {
			include_once('km.php');
			$user = get_user_by( 'id', $user_id );
			$group_object = groups_get_group( array( 'group_id' => $group_id ) );
			$properties = array( 'Group' => $group_object->name, 'Group ID' => $group_id );
			switch ( $reason ) {
				case 'accepted':
					$properties['Comments'] = 'User accepted group invitation';
					// Note: the inviter's user_id has been deleted from the group_membership table before this action, so we can't record the invitation as part of this event, sadly.
					break;
				case 'approved':
					$properties['Comments'] = 'Group admin approved user membership request';
					break;
			}

			KM::init( get_option( 'cc_kissmetrics_key' ) );
			KM::identify( $user->user_email );
			KM::record( 'Joined group', $properties );
		}
		//User accepts invitation
		public function track_accept_group_invite( $user_id, $group_id ) {
			// Use basic join function to record activity
			self::track_join_bp_group( $group_id, $user_id, 'accepted' );
		}
		//User membership request is approved
		public function track_group_membership_request_approval( $user_id, $group_id ) {
			// Use basic join function to record activity
			self::track_join_bp_group( $group_id, $user_id, 'approved' );
		}

		/**
		 * Track when a user leaves a group.
		 */
		public function track_leave_bp_group( $group_id, $user_id, $reason = null ) {
			include_once('km.php');
			$user = get_user_by( 'id', $user_id );
			$group_object = groups_get_group( array( 'group_id' => $group_id ) );
			$properties = array( 'Group' => $group_object->name, 'Group ID' => $group_id );
			switch ( $reason ) {
				case 'removed':
					$properties['Comments'] = 'Removed by group admin';
					break;
				case 'banned':
					$properties['Comments'] = 'Banned by group admin';
					break;
			}

			KM::init( get_option( 'cc_kissmetrics_key' ) );
			KM::identify( $user->user_email );
			KM::record( 'Left group', $properties );
		}
		//User is removed by group admin
		public function track_removed_bp_group( $group_id, $user_id  ) {
			// Use basic leave function to record activity
			self::track_leave_bp_group( $group_id, $user_id, 'removed' );
		}
		//User is banned by group admin
		public function track_banned_bp_group( $group_id, $user_id  ) {
			// Use basic leave function to record activity
			self::track_leave_bp_group( $group_id, $user_id, 'banned' );
		}

		/**
		 * Track when a friendship is created.
		 */
		public function track_create_friendship( $friendship_id, $initiator_user_id, $friend_user_id ) {
			include_once('km.php');
			$initiator = get_user_by( 'id', $initiator_user_id );
			$friend = get_user_by( 'id', $friend_user_id );

			KM::init( get_option( 'cc_kissmetrics_key' ) );
			KM::identify( $initiator->user_email );
			KM::record( 'Created friendship', array( 	'Initiator ID' => $initiator_user_id,
														'Initiator username' => $initiator->user_login,
														'Initiator email' => $initiator->user_email,
														'Friend ID' => $friend_user_id,
														'Friend username' => $friend->user_login,
														'Friend email' => $friend->user_email, )
			);

		}
		/**
		 * Track when a friendship is canceled.
		 */
		public function track_cancel_friendship( $friendship_id, $initiator_user_id, $friend_user_id ) {
			include_once('km.php');
			$initiator = get_user_by( 'id', $initiator_user_id );
			$friend = get_user_by( 'id', $friend_user_id );

			KM::init( get_option( 'cc_kissmetrics_key' ) );
			KM::identify( $initiator->user_email );
			KM::record( 'Canceled friendship', array( 	'Initiator ID' => $initiator_user_id,
														'Initiator username' => $initiator->user_login,
														'Initiator email' => $initiator->user_email,
														'Friend ID' => $friend_user_id,
														'Friend username' => $friend->user_login,
														'Friend email' => $friend->user_email, )
			);
		}

		/**
		* BuddyPress Docs - Track creation and editing of docs.
		*/
		public function track_new_bp_doc( $args ) {

			include_once('km.php');

			$doc_id = $args->doc_id;
			$user_id = get_post_meta( $doc_id, 'bp_docs_last_editor', true );
						$friend = get_user_by( 'id', $friend_user_id );
			$user = get_user_by( 'id', $user_id );

			if ( $group_id = bp_docs_get_associated_group_id( $doc_id ) ) {
				$group_object = groups_get_group( array( 'group_id' => $group_id ) );
				$properties = array( 'Group' => $group_object->name, 'Group ID' => $group_id );
			}

			if ( $args->is_new_doc ) {
				$event = "Created new BuddyPress Doc.";
			} else {
				$event = "Edited BuddyPress Doc.";
			}

			KM::init( get_option( 'cc_kissmetrics_key' ) );
			KM::identify( $user->user_email );
			KM::record( $event, $properties );

		}


		/**
		 * Track when a user votes in the Salud America video contest.
		 * @param 	WP_User  $member WP_User object.
		 */
		public function track_sa_video_contest_vote( $user ) {
			include_once('km.php');

			KM::init( get_option( 'cc_kissmetrics_key' ) );
			KM::identify( $user->user_email );
			KM::record( 'Voted in Salud America video contest' );
		}


		/**
		* Activity stream - Track posts and replies (separately).
		*/
		public function track_activity_stream_posts( $args ) {
			// We only care about some activity types
			// $args['type'] => activity_update 
			//		&& [component] => activity is a post to the user's stream
			// 		&& [component] => groups is a post to a group's stream
			// $args['type'] => activity_comment 
			//		&& [component] => activity is a reply to an activity update in the user's stream OR in a group stream
			$event = '';

			if ( $args['type'] == 'activity_update' ) {
				if ( $args['component'] == 'activity' ) {
					$event = 'Posted activity stream update.';
				} else if ( $args['component'] == 'groups' ) {
					$event = 'Posted group activity stream update.';
				}
			} else if ( $args['type'] == 'activity_comment' ) {
				$event = 'Replied to activity stream update.';
			}

			if ( $event ) {
				include_once('km.php');
				$user = get_user_by( 'id', $args['user_id'] );

				KM::init( get_option( 'cc_kissmetrics_key' ) );
				KM::identify( $user->user_email );
				KM::record( $event );
			}
		}
		// Track when a user favorites an activity item
		public function track_activity_stream_favorite( $activity_id, $user_id ) {
			include_once('km.php');
			$user = get_user_by( 'id', $user_id );

			KM::init( get_option( 'cc_kissmetrics_key' ) );
			KM::identify( $user->user_email );
			KM::record( 'Favorited an activity stream update.' );
		}
		
		function track_comment_approval( $comment_id, $comment_status ) {
			if ( $comment_status != 'approve' )
				return false;

			include_once('km.php');

			// Get post details
			$comment = get_comment( $comment_id );
			// Post comment was made to
			$post = get_post( $comment->comment_post_ID );

			// Is the post a "feature"?
			if ( 'post' == $post->post_type ) {
				$tag_ids = wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) );
				$featured = in_array( 858, $tag_ids) ? 'yes' : 'no';
			} else {
				$featured = 'no';
			}

			if ( ! $post )
				return false;

			$author = get_user_by( 'id', $post->post_author );

			KM::init( get_option( 'cc_kissmetrics_key' ) );
			KM::identify( $comment->comment_author_email );
			KM::record( 'Commented on item', array( 	'Post ID' => $post->ID,
														'Post title' => $post->post_title,
														'Post type' => $post->post_type,
														'Featured post' => $featured,
														'Author email' => $author->user_email
														 )
			);

		}

		function cogis_new_subgroup_form_submission( $entry, $form ){
			include_once('km.php');
			$user = wp_get_current_user();

			KM::init( get_option( 'cc_kissmetrics_key' ) );
			KM::identify( $user->user_email );
			KM::record( 'Submitted COGIS subgroup request form' );

		}

		// Helper functions
		// If the user is not logged in, you can use this function to get the best value from KISS's cookies
		// From within this class, use $identity = self::read_js_identity()
		public function read_js_identity() {
		  if ( isset( $_COOKIE['km_ni'] ) ) {
		    return $_COOKIE['km_ni'];
		  } else if ( isset( $_COOKIE['km_ai'] ) ) {
		    return $_COOKIE['km_ai'];
		  }
		}

	} // End class 'KM_Filter'
} // End class_exists check

// If the JS URL is defined in the options, set the variable
if( function_exists( 'get_option' ) ) {
	$km_key = get_option( 'cc_kissmetrics_key' );
}

// Current site domain and host
$origin = KM_Filter::get_domain( $_SERVER['HTTP_HOST'] );

// Output analytics to all pages and the login page
	// Page-view listeners
	// - Clicks on Grant Support: http://www.communitycommons.org/chi-planning/
	// - Category archive views
	// - Tag archive views
	// - Custom taxonomy archive views
add_action( 'wp_head', array( 'KM_Filter', 'output_analytics' ) );
add_action( 'login_head', array( 'KM_Filter', 'output_analytics' ) );

if( $km_key != '' && function_exists( 'get_option' ) ) {

	// CC event tracking *****************************************************************
	// Registration, account deletion
	add_action( 'bp_core_signup_user', array( 'KM_Filter', 'track_registration_bp' ), 17 );
	add_action( 'delete_user', array( 'KM_Filter', 'track_user_account_delete' ), 17 );

	//Signing in
	add_action( 'wp_login', array( 'KM_Filter', 'track_cc_login' ), 45, 2 );

	// Group joining and leaving
	add_action( 'groups_join_group', array( 'KM_Filter', 'track_join_bp_group' ), 17, 2 );
	add_action( 'groups_accept_invite', array( 'KM_Filter', 'track_accept_group_invite' ), 22, 2 );
	add_action( 'groups_membership_accepted', array( 'KM_Filter', 'track_group_membership_request_approval' ), 22, 2 );
	// Leaving, being removed, or banned
	add_action( 'groups_leave_group', array( 'KM_Filter', 'track_leave_bp_group' ), 17, 2 );
	add_action( 'groups_remove_member', array( 'KM_Filter', 'track_removed_bp_group' ), 17, 2 );
	add_action( 'groups_ban_member', array( 'KM_Filter', 'track_banned_bp_group' ), 17, 2 );

	// Friendships
	add_action( 'friends_friendship_accepted', array( 'KM_Filter', 'track_create_friendship' ), 17, 3 );
	add_action( 'friends_friendship_deleted', array( 'KM_Filter', 'track_cancel_friendship' ), 17, 3 );

	//BuddyPress Docs
	add_action( 'bp_docs_doc_saved', array( 'KM_Filter', 'track_new_bp_doc' ) );

	//BuddyPress Activity Stream
	add_action( 'bp_activity_add', array( 'KM_Filter', 'track_activity_stream_posts' ), 12, 3 );
	add_action( 'bp_activity_add_user_favorite', array( 'KM_Filter', 'track_activity_stream_favorite' ), 12, 2 );

	// Comments
	add_action( 'wp_set_comment_status', array( 'KM_Filter', 'track_comment_approval' ), 17, 2 );

	// Salud America
	// Voted on video contest
	add_action( 'after_sa_video_vote', array( 'KM_Filter', 'track_sa_video_contest_vote' ), 17, 1 );

    // Track GOGIS request group form submission
    // 17 is the form id, so this will only fire on that form's submission
	add_action( 'gform_after_submission_17', array( 'KM_Filter', 'cogis_new_subgroup_form_submission' ), 10, 2);    
}
