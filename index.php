<?php 
/**
 * Plugin Name: WPCode Content Ratio
 * Description: This plugin allows you to check the content code ratio. Specially useful to know if your post is good for search engines.
 * Version: 2.0
 * Plugin URI: http://jonatanjumbert.com/blog/wordpress/wpcode-content-ratio/?utm_source=Wordpress&amp;utm_medium=Plugin&amp;utm_term=WPCode%20Content%20Ratio&amp;utm_campaign=Wordpress%20plugins
 * Author: Jonatan Jumbert
 * Author URI: http://jonatanjumbert.com/?utm_source=Wordpress&amp;utm_medium=Plugin&amp;utm_term=WPCode%20Content%20Ratio&amp;utm_campaign=Wordpress%20plugins
 * GPLv2 or later
 */

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

define('WPCODE_PLUGIN_URI', plugins_url('', __FILE__));
define('WPCODE_PLUGIN_PATH', dirname(__FILE__));
define('WPCODE_MEDIA_URI', trailingslashit(WPCODE_PLUGIN_URI) . 'media');
define('WPCODE_MEDIA_PATH', trailingslashit(WPCODE_PLUGIN_PATH) . 'media');
if(!defined('MYPLUGIN_VERSION_KEY')) define('WPCODE_VERSION_KEY', 'myplugin_version');
if(!defined('MYPLUGIN_VERSION_NUM')) define('WPCODE_VERSION_NUM', '2.0');
add_option(WPCODE_VERSION_KEY, WPCODE_VERSION_NUM);

$non_code_content_ratio_post_types = array('attachment', 'revision', 'nav_menu_item');
$non_code_content_ratio_taxonomies = array('category', 'post_tag', 'nav_menu', 'link_category', 'post_format');

register_activation_hook(__FILE__, 'jja_install_wpcode');
register_uninstall_hook(__FILE__, 'jja_uninstall_wpcode');
register_deactivation_hook(__FILE__, 'jja_uninstall_wpcode');

$currentLocale = get_locale();
$moFile = (!empty($currentLocale)) ? dirname(__FILE__) . "/lang/wpcode-" . $currentLocale . ".mo" : dirname(__FILE__) . "/lang/wpcode-en_EN.mo";
if(@file_exists($moFile) && is_readable($moFile)) load_textdomain('wpcode-content-ratio', $moFile);

/**
 * Install function with default plugin options
 * @return unknown_type
 */
function jja_install_wpcode() {
	$values = array(
		'calculate_on_listing' => true,
		'calculate_categories' => false,
		'calculate_tags' => false,
		'calculate_taxonomies' => array(),
		'calculate_authors' => false,
		'calculate_on_save' => true,
		'calculate_post_types' => array('post', 'page'),
		'options_checked' => false
	);
	add_option('wpcode_options', $values);
}

/**
 * Uninstall plugin functions, delete configuration and all saved ratios.
 * @return unknown_type
 */
function jja_uninstall_wpcode() {
	global $wpdb;
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", 'ratio'));
	
	$options = get_option('wpcode_options');
	
	delete_option('wpcode_options');
	delete_option('wpcode_tags_ratio');
	delete_option('wpcode_categories_ratio');
	delete_option('wpcode_author_ratio');
	foreach($options['calculate_taxonomies'] as $t) {
		delete_option('wpcode_tax_'.$t.'_ratio');
	}
}
$plugin_options = get_option('wpcode_options');

/*################################################################################################################################################*/
add_filter('plugin_action_links', 'jja_wpcode_action_links', 10, 2);

function jja_wpcode_action_links($links, $file) {
    static $this_plugin;
    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }
    if($file == $this_plugin) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wpcode-content-ratio&pageview=setup">'.__('Settings', 'wpcode-content-ratio').'</a>';
        $help_link = '<a href="http://jonatanjumbert.com/blog/wordpress/wpcode-content-ratio/?utm_source=Wordpress&amp;utm_medium=Plugin&amp;utm_term=WPCode%20Content%20Ratio&amp;utm_campaign=Wordpress%20plugins" target="_blank">'.__('Help', 'wpcode-content-ratio').'</a>';
        array_unshift($links, $settings_link);
        array_unshift($links, $help_link);
    }
    return $links;
}

/*################################################################################################################################################*/
add_action('admin_menu', 'jja_wpcode_config_page');

/**
 * Set plugin config page on wordpress menu
 * @return unknown_type
 */
function jja_wpcode_config_page() {
	add_menu_page(__('WPCode Content Ratio', 'wpcode-content-ratio'), __('Content Ratio', 'wpcode-content-ratio'), 'activate_plugins', 'wpcode-content-ratio', 'jja_wpcode_content_ratio_page', WPCODE_MEDIA_URI.'/img/icono.png');
}

/**
 * Plugin config page 
 * @return unknown_type
 */
function jja_wpcode_content_ratio_page() {
	wp_enqueue_style('wpcode-content-ratio.css', WPCODE_MEDIA_URI.'/css/wpcode-content-ratio.css', false, WPCODE_VERSION_NUM, false);
	wp_enqueue_style('bootstrap.min.css', WPCODE_MEDIA_URI.'/css/bootstrap.min.css', false, WPCODE_VERSION_NUM, false);
	wp_register_script('wpcode-content-ratio', WPCODE_MEDIA_URI.'/js/wpcode-content-ratio.src.js', array('jquery'), WPCODE_VERSION_NUM, false);
	wp_enqueue_script('wpcode-content-ratio');
	
	if(isset($_POST['submit'])) jja_save_wpcode_options($_POST);
	if(isset($_GET['all_categories'])) jja_calc_all_categories();
	if(isset($_GET['category'])) jja_calc_category($_GET['category']);
	if(isset($_GET['clean_categories'])) jja_clean_categories();
	if(isset($_GET['all_tags'])) jja_calc_all_tags();
	if(isset($_GET['tag'])) jja_calc_tag($_GET['tag']);
	if(isset($_GET['clean_tags'])) jja_clean_tags();
	if(isset($_GET['post_type'])) jja_calc_per_post_type($_GET['post_type']);
	if(isset($_GET['clean_post_type'])) jja_clean_post_type($_GET['clean_post_type']);
	if(isset($_GET['home'])) jja_calc_home_ratio();
	if(isset($_GET['all_authors'])) jja_calc_all_authors();
	if(isset($_GET['author'])) jja_calc_author($_GET['author']);
	if(isset($_GET['clean_authors'])) jja_clean_authors();
	if(isset($_GET['all_taxonomies'])) jja_calc_all_taxonomies($_GET['all_taxonomies']);
	if(isset($_GET['clean_taxonomies'])) jja_clean_taxonomies($_GET['clean_taxonomies']);
	if(isset($_GET['taxonomy']) && isset($_GET['term_id'])) jja_calc_taxonomy_term($_GET['taxonomy'], $_GET['term_id']); 
	
	global $non_code_content_ratio_post_types;
	global $non_code_content_ratio_taxonomies;
	global $plugin_options; ?>
	<div class="wrap">
		<h2><?php _e('WPCode Content Ratio', 'wpcode-content-ratio'); ?></h2>
		<ul class="subsubsub">
			<li>
				<a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&viewpage=purpose" <?php if((isset($_GET['viewpage']) && $_GET['viewpage'] == "purpose") || !isset($_GET['viewpage'])) { echo 'class="current"'; } ?>><?php _e('Purpose', 'wpcode-content-ratio');?></a> |
			</li>
			<li>
				<a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&viewpage=setup" <?php if(isset($_GET['viewpage']) && $_GET['viewpage'] == "setup") { echo 'class="current"'; } ?>><?php _e('Setup', 'wpcode-content-ratio');?></a> |
			</li><?php
			if($plugin_options['calculate_categories'] == true) : ?>
				<li><a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&viewpage=category" <?php if(isset($_GET['viewpage']) && $_GET['viewpage'] == "category") { echo 'class="current"'; } ?>><?php _e('Categories ratio', 'wpcode-content-ratio'); ?></a> | </li><?php
			endif;
			if($plugin_options['calculate_tags'] == true) : ?>
				<li><a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&viewpage=tags" <?php if(isset($_GET['viewpage']) && $_GET['viewpage'] == "tags") { echo 'class="current"'; } ?>><?php _e('Tags ratio', 'wpcode-content-ratio'); ?></a> | </li><?php
			endif;
			if($plugin_options['calculate_authors'] == true) : ?>
				<li><a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&viewpage=authors" <?php if(isset($_GET['viewpage']) && $_GET['viewpage'] == "authors") { echo 'class="current"'; } ?>><?php _e('Authors ratio', 'wpcode-content-ratio'); ?></a> | </li><?php
			endif;
			if(!empty($plugin_options['calculate_taxonomies'])) :
				foreach($plugin_options['calculate_taxonomies'] as $taxonomy) : ?>
					<?php $tax = get_taxonomy($taxonomy); ?>
					<?php if(!empty($tax)) : ?>
						<li>
							<a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&viewpage=taxonomy&slug=<?php echo $taxonomy; ?>" <?php if((isset($_GET['viewpage']) && $_GET['viewpage'] == "taxonomy") && (isset($_GET['slug']) && $_GET['slug'] == $taxonomy)) { echo 'class="current"'; } ?>">
								<?php echo $tax->label; ?>									
							</a> |
						</li><?php
					endif;
				endforeach;
			endif; ?>
		</ul>
		
		<div id="dashboard-widgets-wrap">
			<div id="dashboard-widgets" class="metabox-holder">
				<div class="postbox-container" style="width:75%"><?php 
					if(isset($_GET['donation'])) :
						jja_wpcode_donation_thanks();
					else : 
						if((isset($_GET['viewpage']) && $_GET['viewpage'] == 'purpose') || !isset($_GET['viewpage'])) : 
							jja_wpcode_view_purpose($plugin_options);
						endif;
						if(isset($_GET['viewpage']) && $_GET['viewpage'] == 'setup') :
							jja_wpcode_configuration($non_code_content_ratio_post_types, $non_code_content_ratio_taxonomies, $plugin_options);
						endif;
						if(isset($_GET['viewpage']) && $_GET['viewpage'] == 'category') :
							jja_wpcode_view_category();
						endif;
						if(isset($_GET['viewpage']) && $_GET['viewpage'] == 'tags') :
							jja_wpcode_view_tag();
						endif;
						if(isset($_GET['viewpage']) && $_GET['viewpage'] == 'authors') :
							jja_wpcode_view_authors();
						endif;
						if(isset($_GET['viewpage']) && $_GET['viewpage'] == 'taxonomy') :
							jja_wpcode_view_taxonomy($plugin_options, $_GET['slug']);
						endif;
					endif;
					?> 
				</div>
				<?php jja_print_sidebar_left(); ?>
			</div>
		</div>
	</div><?php
}

function jja_wpcode_donation_thanks() { ?>
	<table class="widefat" cellspacing="0">
		<thead>
			<tr>
				<th scope="col" colspan="4" class="important">
					<span><strong><?php _e('Thank you very much for your donation', 'wpcode-content-ratio'); ?></strong></span> 
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td colspan="4">
					<p><?php _e("Thank you very much! New features will be available soon...", 'wpcode-content-ratio'); ?></p>
					<p><?php _e('If you wanna make some request... this would be taken into account first.', 'wpcode-content-ratio'); ?>
				</td>
			</tr>
		</tbody>
	</table><?php
}


function jja_wpcode_view_purpose($plugin_options) { ?>
	<table class="widefat" cellspacing="0">
		<thead>
			<tr>
				<th scope="col" colspan="4" class="important">
					<span><strong><?php _e('What is the Code to Text Ratio', 'wpcode-content-ratio'); ?></strong> : <?php _e('Why is it important?', 'wpcode-content-ratio');?></span> 
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td colspan="4">
					<ul>
						<li><?php _e('The Code to Text Ratio represents the percentage of actual text in a web page. This plugin extracts the text of all your pages and posts from HTML code and calculates the content ratio based on this information.', 'wpcode-content-ratio');?> <?php _e('The code to text ratio of a page is used by search engines and spiders to calculate the relevancy of a web page. A higher code to text ratio gives you a better chance of getting a good page ranking for your page.', 'wpcode-content-ratio');?></li>
						<li><?php _e('Not all search engines are using the code to text ratio in their index algorithm, but most of them do. So having a higher code to text ratio than your competitors gives you a good start for on-site optimization. ', 'wpcode-content-ratio');?></li>
					</ul>
				</td>
			</tr>
			<tr>
				<td colspan="4">
					<p><strong><?php 
						_e('Your home page code to text ratio is: ', 'wpcode-content-ratio');
							if(isset($plugin_options['home_ratio'])) : ?>
							<span <?php echo jja_determine_color($plugin_options['home_ratio']); ?>><strong><?php echo $plugin_options['home_ratio']; ?> %</strong></span><?php 
						else :
							$home_ratio = jja_check_ratio(get_bloginfo('wpurl'));
							$plugin_options['home_ratio'] = $home_ratio;
							update_option('wpcode_options', $plugin_options); ?>
							<span <?php echo jja_determine_color($home_ratio); ?>><strong><?php echo $home_ratio; ?> %</strong></span><?php
						endif; ?>
						</strong><a href="<?php echo get_bloginfo('wpurl');?>/wp-admin/admin.php?page=wpcode-content-ratio&home=1" style="text-decoration:none;" class="btn btn-success"><?php _e('Check ratio', 'wpcode-content-ratio');?></a>
					</p> 
					<p><?php _e('% Ratios and meanings', 'wpcode-content-ratio'); ?>:</p>
					<ul>
						<li><span class="red bolder">0% - 10%</span>: <?php _e('Consider adding more content or revamping your code.', 'wpcode-content-ratio'); ?></li>
						<li><span class="bolder">10% - 25%</span>: <?php _e('Content is moderate, but always room to improve.', 'wpcode-content-ratio'); ?></li>
						<li><span class="green bolder">25% - 70%</span>: <?php _e("Generally good, but don't over do it.", 'wpcode-content-ratio'); ?></li>
						<li><span class="bolder">70% - 100%:</span> <?php _e("If you've got higher than about 70&#37; or so then you've got a lot of text and not a lot of code, which might sound like a good thing, but could represent spam too.", 'wpcode-content-ratio'); ?></li>
					</ul>
					<br /><p><strong><?php _e('Ways to lower the amount of code on a web page', 'wpcode-content-ratio');?></strong></p>
					<ol>
						<li><?php _e('Use CSS layouts instead of table-based layouts.', 'wpcode-content-ratio'); ?></li>
						<li><?php _e('Put CSS and Javascript into external files.', 'wpcode-content-ratio'); ?></li>
						<li><?php _e('Take out tags that have no purpose.', 'wpcode-content-ratio'); ?></li>
						<li><?php _e('Use valid code. After all, a page scoring 25% which works everywhere is usually better than one which scores 50% but fails in most browsers.', 'wpcode-content-ratio'); ?></li>
						<li><?php _e('Write more text on your posts.', 'wpcode-content-ratio'); ?></li>
					</ol>
				</td>
			</tr>
		</tbody>
	</table><?php
}

function jja_wpcode_configuration($non_code_content_ratio_post_types, $non_code_content_ratio_taxonomies, $plugin_options) { ?>
	<table class="widefat" cellspacing="0">
		<thead>
			<tr>
				<th scope="col" colspan="4" class="important">
					<span><?php _e('<strong>WPCode Content Ratio</strong>: SETUP', 'wpcode-content-ratio');?></span> 
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td colspan="4">
					<form method="post" action="<?php echo get_bloginfo('wpurl');?>/wp-admin/admin.php?page=wpcode-content-ratio&viewpage=setup"><?php
						$checked_listing = (isset($plugin_options['calculate_on_listing']) && $plugin_options['calculate_on_listing'] == true) ? ' checked="checked"' : '';
						$checked_save = (isset($plugin_options['calculate_on_save']) && $plugin_options['calculate_on_save'] == true) ? ' checked="checked"' : '';
						$checked_categories = (isset($plugin_options['calculate_categories']) && $plugin_options['calculate_categories'] == true) ? ' checked="checked"' : '';
						$checked_tags = (isset($plugin_options['calculate_tags']) && $plugin_options['calculate_tags'] == true) ? ' checked="checked"' : '';
						$checked_authors = (isset($plugin_options['calculate_authors']) && $plugin_options['calculate_authors'] == true) ? ' checked="checked"' : ''; ?>
						<table>
							<tr>
								<td><input name="calculate_on_listing" <?php echo $checked_listing; ?> type="checkbox" value="1" /> <?php _e('After listing posts/pages/custom posts calculate automatically code to content ratio.', 'wpcode-content-ratio'); ?></td>
							</tr>
							<tr>
								<td><input name="calculate_on_save" <?php echo $checked_save; ?> type="checkbox" value="1" /> <?php _e('After save a post/page/custom post calculate automatically code to content ratio.', 'wpcode-content-ratio'); ?></td>
							</tr>
							<tr>
								<td><input name="calculate_categories" <?php echo $checked_categories; ?> type="checkbox" value="1" /> <?php _e('Check to calculate code to content ratio for categories.', 'wpcode-content-ratio'); ?></td>
							</tr>
							<tr>
								<td><input name="calculate_tags" <?php echo $checked_tags; ?> type="checkbox" value="1" /> <?php _e('Check to calculate code to content ratio for tags.', 'wpcode-content-ratio'); ?></td>
							</tr>
							<tr>
								<td><input name="calculate_authors" <?php echo $checked_authors; ?> type="checkbox" value="1" /> <?php _e("Check to calculate code to content ratio for author's page.", 'wpcode-content-ratio'); ?></td>
							</tr>
							<tr>
								<td><?php 
									$taxonomies = get_taxonomies(); 
									$parsed_tax = array_diff($taxonomies, $non_code_content_ratio_taxonomies); 
									$tax_text = (!empty($parsed_tax)) ? __('and taxonomies', 'wpcode-content-ratio') : ''; ?>
									<?php printf(__('Select to calculate code to content ratio for this post types %s: ', 'wpcode-content-ratio'), $tax_text); ?><br />
									<select name="calculate_post_types[]" multiple="multiple" style="width:200px;"><?php 
										$post_types = get_post_types();
										foreach($post_types as $post_type) :
											if(!in_array($post_type, $non_code_content_ratio_post_types)) :
												if(in_array($post_type, $plugin_options['calculate_post_types'])) : ?>
													<option value="<?php echo $post_type; ?>" selected="selected"><?php echo $post_type; ?></option><?php 
												else : ?>
													<option value="<?php echo $post_type; ?>"><?php echo $post_type; ?></option><?php
												endif;
											endif;
										endforeach; ?>
									</select>
									<?php 
										if(!empty($parsed_tax)) : ?>
										<select name="calculate_taxonomies[]" multiple="multiple" style="width:200px;"><?php 
											foreach($parsed_tax as $taxonomy) :
												if(in_array($taxonomy, $plugin_options['calculate_taxonomies'])) : ?>
													<option value="<?php echo $taxonomy; ?>" selected="selected"><?php echo $taxonomy; ?></option><?php 
												else : ?>
													<option value="<?php echo $taxonomy; ?>"><?php echo $taxonomy; ?></option><?php
												endif;
											endforeach; ?>
										</select>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td><input type="submit" name="submit" class="btn btn-success" value="<?php _e('Save options', 'wpcode-content-ratio');?>" /></td>
							</tr>
						</table>
					</form>
				</td>
			</tr>
			<tr>
				<td colspan=4">
					<h2><?php _e('Calculate Code to Content Ratio for: ', 'wpcode-content-ratio'); ?></h2>
					<p><?php _e('This action may take several minutes depending your server speed and the number of entries you have. Be patient while the operation is in progress.', 'wpcode-content-ratio'); ?></p>
					<?php 
						$post_types = get_post_types();
						$real_post_types = array_diff($post_types , $non_code_content_ratio_post_types);
						$not_showing = array_diff($real_post_types, isset($plugin_options['calculate_post_types']) ? $plugin_options['calculate_post_types'] : array());
						foreach($real_post_types as $post_type) :
							if(!in_array($post_type, $not_showing)) :
								$obj = get_post_type_object($post_type); ?>
								<a class="btn btn-primary" href="<?php echo get_bloginfo('wpurl');?>/wp-admin/admin.php?page=wpcode-content-ratio&post_type=<?php echo $post_type; ?>&viewpage=setup"><?php echo $obj->labels->singular_name; ?></a>&nbsp;<?php
							endif; 
						endforeach; 
					?>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<h2><?php _e('Clean Code to Content Ratio for: ', 'wpcode-content-ratio'); ?></h2>
					<p><?php _e('This action will delete all ratios for post types', 'wpcode-content-ratio'); ?></p>
					<?php 
						foreach($real_post_types as $post_type) :
							if(!in_array($post_type, $not_showing)) :
								$obj = get_post_type_object($post_type); ?>
								<a class="btn btn-danger" href="<?php echo get_bloginfo('wpurl');?>/wp-admin/admin.php?page=wpcode-content-ratio&clean_post_type=<?php echo $post_type; ?>&viewpage=setup"><?php echo $obj->labels->singular_name; ?></a>&nbsp;<?php
							endif;
						endforeach; 
					?>
				</td>
			</tr>
		</tbody>
	</table><?php
}

function jja_wpcode_view_authors() { ?>
	<table class="widefat" cellspacing="0">
		<thead>
			<tr>
				<th scope="col" colspan="4" class="important">
					<span><strong><?php _e('You are viewing this page because you already checked on plugin setup.', 'wpcode-content-ratio'); ?></span> 
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td colspan="4">
					<?php _e('This action may take several minutes depending your server speed and the number of authors your site have. Be patient while the operation is in progress.', 'wpcode-content-ratio'); ?>
				</td>
			</tr>
			<tr>
				<td colspan="4">
					<?php $users = get_users(); ?>
					<?php $users_ratio = get_option('wpcode_author_ratio'); ?>
					<?php if(!empty($users)) : ?>
						<a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&all_authors=1&viewpage=authors" class="btn btn-success">
							<?php _e("Calculate all user's page ratio", 'wpcode-content-ratio');?>
						</a>&nbsp;
						<a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&clean_authors=1&viewpage=authors" class="btn btn-danger">
							<?php _e("Clean all user's page ratio", 'wpcode-content-ratio'); ?>
						</a><br /><br />
						<table class="widefat" cellspacing="0">
							<thead class="ui-widget-header">
								<tr class="alt">
									<th><?php _e('User Name', 'wpcode-content-ratio'); ?></th>
									<th><?php _e('User URL', 'wpcode-content-ratio'); ?></th>
									<th><?php _e('User Ratio', 'wpcode-content-ratio'); ?></th>
									<th><?php _e('Check Ratio', 'wpcode-content-ratio'); ?></th>
								</tr>
							</thead>
							<tbody class="ui-widget-content">
								<?php foreach($users as $user) : ?>
									<tr>
										<td><?php echo $user->user_nicename; ?></td>
										<td><a href="<?php echo get_author_posts_url($user->ID); ?>" target="_blank" title="<?php echo $user->user_nicename; ?>"><?php echo get_author_posts_url($user->ID); ?></a></td>
										<td class="center">
											<?php if(!$users_ratio) : ?>
												<?php _e('Empty', 'wpcode-content-ratio'); ?>
											<?php else : ?>
												<?php if(isset($users_ratio[$user->ID])) : ?>
													<span <?php echo jja_determine_color($users_ratio[$user->ID]); ?>><?php echo $users_ratio[$user->ID]; ?> %</span>
												<?php else : ?>
													<?php _e('Empty', 'wpcode-content-ratio'); ?>
												<?php endif; ?>
											<?php endif; ?>
										</td>
										<td><a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&author=<?php echo $user->ID; ?>&viewpage=authors" title="<?php _e('Check ratio', 'wpcode-content-ratio'); ?>"><?php _e('Check', 'wpcode-content-ratio'); ?></a></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<strong><?php _e("Your site don't have users.", 'wpcode-content-ratio'); ?></strong>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table><?php
}

function jja_wpcode_view_tag() { ?>
	<table class="widefat" cellspacing="0">
		<thead>
			<tr>
				<th scope="col" colspan="4" class="important">
					<span><strong><?php _e('You are viewing this page because you already checked on plugin setup.', 'wpcode-content-ratio'); ?></span> 
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td colspan="4">
					<?php _e('This action may take several minutes depending your server speed and the number of tags your site have. Be patient while the operation is in progress.', 'wpcode-content-ratio'); ?>
				</td>
			</tr>
			<tr>
				<td colspan="4">
					<?php $tags = get_tags(); ?>
					<?php $tags_ratio = get_option('wpcode_tags_ratio'); ?>
					<?php if(!empty($tags)) : ?>
						<a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&all_tags=1&viewpage=tags" class="btn btn-success">
							<?php _e('Calculate all tags ratio', 'wpcode-content-ratio');?>
						</a>&nbsp;
						<a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&clean_tags=1&viewpage=tags" class="btn btn-danger">
							<?php _e('Clean all tags ratio', 'wpcode-content-ratio'); ?>
						</a><br /><br />
						<table class="widefat" cellspacing="0">
							<thead class="ui-widget-header">
								<tr class="alt">
									<th><?php _e('Tag Name', 'wpcode-content-ratio'); ?></th>
									<th><?php _e('Tag URL', 'wpcode-content-ratio'); ?></th>
									<th><?php _e('Tag Ratio', 'wpcode-content-ratio'); ?></th>
									<th><?php _e('Check Ratio', 'wpcode-content-ratio'); ?></th>
								</tr>
							</thead>
							<tbody class="ui-widget-content">
								<?php foreach($tags as $tag) : ?>
									<tr>
										<td><?php echo $tag->name; ?></td>
										<td><a href="<?php echo get_tag_link($tag->term_id); ?>" target="_blank" title="<?php echo $tag->name; ?>"><?php echo get_tag_link($tag->term_id); ?></a></td>
										<td class="center">
											<?php if(!$tags_ratio) : ?>
												<?php _e('Empty', 'wpcode-content-ratio'); ?>
											<?php else : ?>
												<?php if(isset($tags_ratio[$tag->term_id])) : ?>
													<span <?php echo jja_determine_color($tags_ratio[$tag->term_id]); ?>><?php echo $tags_ratio[$tag->term_id]; ?> %</span>
												<?php else : ?>
													<?php _e('Empty', 'wpcode-content-ratio'); ?>
												<?php endif; ?>
											<?php endif; ?>
										</td>
										<td><a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&tag=<?php echo $tag->term_id; ?>&viewpage=tags" title="<?php _e('Check ratio', 'wpcode-content-ratio'); ?>"><?php _e('Check', 'wpcode-content-ratio'); ?></a></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table><?php
					else : ?>
						<strong><?php _e("You don't have tags", 'wpcode-content-ratio'); ?></strong>
					<?php endif;?>
				</td>
			</tr>
		</tbody>
	</table><?php
}

function jja_wpcode_view_category() { ?>
	<table class="widefat" cellspacing="0">
		<thead>
			<tr>
				<th scope="col" colspan="4" class="important">
					<span><strong><?php _e('You are viewing this page because you already checked on plugin setup.', 'wpcode-content-ratio'); ?></span> 
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td colspan="4">
					<?php _e('This action may take several minutes depending your server speed and the number of categories your site have. Be patient while the operation is in progress.', 'wpcode-content-ratio'); ?>
				</td>
			</tr>
			<tr>
				<td colspan="4">
					<?php $categories = get_categories(); ?>
					<?php $categories_ratio = get_option('wpcode_categories_ratio'); ?>
					<?php if(!empty($categories)) : ?>
						<a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&all_categories=1&viewpage=category" class="btn btn-success">
							<?php _e('Calculate all categories ratio', 'wpcode-content-ratio');?>
						</a>&nbsp;
						<a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&clean_categories=1&viewpage=category" class="btn btn-danger">
							<?php _e('Clean all categories ratio', 'wpcode-content-ratio'); ?>
						</a><br /><br />
						<table class="widefat" cellspacing="0">
							<thead class="ui-widget-header">
								<tr class="alt">
									<th><?php _e('Category Name', 'wpcode-content-ratio'); ?></th>
									<th><?php _e('Category URL', 'wpcode-content-ratio'); ?></th>
									<th><?php _e('Category Ratio', 'wpcode-content-ratio'); ?></th>
									<th><?php _e('Check Ratio', 'wpcode-content-ratio'); ?></th>
								</tr>
							</thead>
							<tbody class="ui-widget-content">
								<?php foreach($categories as $cat) : ?>
									<tr>
										<td><?php echo $cat->name; ?></td>
										<td><a href="<?php echo get_category_link($cat->term_id); ?>" target="_blank" title="<?php echo $cat->name; ?>"><?php echo get_category_link($cat->term_id); ?></a></td>
										<td class="center">
											<?php if(!$categories_ratio) : ?>
												<?php _e('Empty', 'wpcode-content-ratio'); ?>
											<?php else : ?>
												<?php if(isset($categories_ratio[$cat->term_id])) : ?>
													<span <?php echo jja_determine_color($categories_ratio[$cat->term_id]); ?>><?php echo $categories_ratio[$cat->term_id]; ?> %</span>
												<?php else : ?>
													<?php _e('Empty', 'wpcode-content-ratio'); ?>
												<?php endif; ?>
											<?php endif; ?>
										</td>
										<td><a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&category=<?php echo $cat->term_id; ?>&viewpage=category" title="<?php _e('Check ratio', 'wpcode-content-ratio'); ?>"><?php _e('Check', 'wpcode-content-ratio'); ?></a></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table><?php
					else : ?>
						<strong><?php _e("You don't have categories", 'wpcode-content-ratio'); ?></strong>
					<?php endif;?>
				</td>
			</tr>
		</tbody>
	</table><?php
}

function jja_wpcode_view_taxonomy($plugin_options, $tax_slug) { ?>
	<table class="widefat" cellspacing="0">
		<thead>
			<tr>
				<th scope="col" colspan="4" class="important">
					<span><strong><?php _e('You are viewing this page because you already checked on plugin setup.', 'wpcode-content-ratio'); ?></span> 
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td colspan="4">
					<?php _e('This action may take several minutes depending your server speed and the number of terms your site have. Be patient while the operation is in progress.', 'wpcode-content-ratio'); ?>
				</td>
			</tr>
			<tr>
				<td colspan="4"><?php
					$terms = get_terms($tax_slug);
					$taxonomy_ratio = get_option('wpcode_tax_'.$tax_slug.'_ratio');
					$current_tax = get_taxonomy($tax_slug);
					if(!empty($terms)) : ?>
						<a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&all_taxonomies=<?php echo $tax_slug; ?>&viewpage=taxonomy&slug=<?php echo $tax_slug; ?>" class="btn btn-success">
							<?php printf(__('Calculate all %s ratio', 'wpcode-content-ratio'), $current_tax->label); ?>
						</a>&nbsp;
						<a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&clean_taxonomies=<?php echo $tax_slug; ?>&viewpage=taxonomy&slug=<?php echo $tax_slug; ?>" class="btn btn-danger">
							<?php printf(__('Clean all %s ratio', 'wpcode-content-ratio'), $current_tax->label); ?>
						</a><br /><br />
						<table class="widefat" cellspacing="0">
							<thead class="ui-widget-header">
								<tr class="alt">
									<th><?php _e('Term Name', 'wpcode-content-ratio'); ?></th>
									<th><?php _e('Term URL', 'wpcode-content-ratio'); ?></th>
									<th><?php _e('Term Ratio', 'wpcode-content-ratio'); ?></th>
									<th><?php _e('Check Ratio', 'wpcode-content-ratio'); ?></th>
								</tr>
							</thead>
							<tbody class="ui-widget-content">
								<?php foreach($terms as $term) : ?>
									<tr>
										<td><?php echo $term->name; ?></td>
										<td><a href="<?php echo get_term_link($term); ?>" target="_blank" title="<?php echo $term->name; ?>"><?php echo get_term_link($term); ?></a></td>
										<td class="center">
											<?php if(!$taxonomy_ratio) : ?>
												<?php _e('Empty', 'wpcode-content-ratio'); ?>
											<?php else : ?>
												<?php if(isset($taxonomy_ratio[$term->term_id])) : ?>
													<span <?php echo jja_determine_color($taxonomy_ratio[$term->term_id]); ?>><?php echo $taxonomy_ratio[$term->term_id]; ?> %</span>
												<?php else : ?>
													<?php _e('Empty', 'wpcode-content-ratio'); ?>
												<?php endif; ?>
											<?php endif; ?>
										</td>
										<td><a href="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&taxonomy=<?php echo $tax_slug; ?>&term_id=<?php echo $term->term_id; ?>&viewpage=taxonomy&slug=<?php echo $tax_slug; ?>" title="<?php _e('Check ratio', 'wpcode-content-ratio'); ?>"><?php _e('Check', 'wpcode-content-ratio'); ?></a></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<strong><?php printf(__("Your site don't have terms under <strong>%s</strong> taxonomy.", 'wpcode-content-ratio'), $current_tax->label); ?></strong>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table><?php
}

/**
 * Prints left sidebar on admin plugin page 
 * @return unknown_type
 */
function jja_print_sidebar_left() {
	$locale = get_locale(); ?>
	<div id="postbox-container-1" class="postbox-container" style="width:25%">
		<div id="normal-sortables" class="meta-box-sortables ui-sortable">
			<div id="dashboard_quick_press" class="postbox ">
				<h3 style="cursor:auto;"><?php _e('About the author', 'wpcode-content-ratio'); ?></h3>
				<div class="inside">
					<form name="post" action="https://www.paypal.com/cgi-bin/webscr" method="post" id="quick-press" class="initial-form hide-if-no-js">
						<div id="author_desc">
							<img src="<?php echo TSC_MEDIA_URI; ?>/img/plugin-author.jpg" class="foto-author"/>
							<p><?php _e('My name is <strong>Jonatan Jumbert</strong>, I am SEO professional and a web developer who enjoys to take on new projects. If do you want know more about me you can check out my <a href="https://plus.google.com/u/0/+JonatanJumbert/posts" titlte="Author Google+ profile" target="_blank">Google+ profile</a> or me <a href="http://jonatanjumbert.com?utm_source=Wordpress&utm_medium=Plugin&utm_term=WPCode%20Content%20Ratio&utm_campaign=Wordpress%20plugins" target="_blank" title="Author site">personal site</a>.', 'wpcode-content-ratio'); ?> <strong><?php _e('Do you wanna hire me?', 'wpcode-content-ratio');?></strong></p>
						</div>
						<div class="sidebar-name">
							<h3><?php _e('Make a donation', 'wpcode-content-ratio'); ?></h3>
						</div>
						<div id="sidebar-2" style="min-height: 50px; ">
							<div class="sidebar-description">
								<p class="description"><?php _e("Do you like this plugin? Why don't contribute with a little donation?", 'wpcode-content-ratio'); ?></p>
								<p class="description"><?php _e('Make author happy and pay him something', 'wpcode-content-ratio');?>.</p>
								<div class="paypal-donations">
									<input type="hidden" name="cmd" value="_donations" />
									<input type="hidden" name="business" value="jonatan.jumbert@gmail.com" />
									<input type="hidden" name="item_name" value="WPCode Content Ratio" />
									<input type="hidden" name="return" value="<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=wpcode-content-ratio&donation=thanks" />
									<?php if($locale == 'en_US') : ?>
										<select name="amount">
											<option value="1"><?php _e('Pay a coffee - &dollar;1,00 USD', 'wpcode-content-ratio'); ?></option>
											<option value="2"><?php _e('Pay a beer - &dollar;2,00 USD', 'wpcode-content-ratio'); ?></option>
											<option value="3"><?php _e('Pay a snack - &dollar;3,00 USD', 'wpcode-content-ratio'); ?></option>
											<option value="5"><?php _e('Pay a drink - &dollar;5,00 USD', 'wpcode-content-ratio'); ?></option>
											<option value="10"><?php _e('Pay the cinema - &dollar;10,00 USD', 'wpcode-content-ratio'); ?></option>
										</select><br />
										<input type="hidden" name="currency_code" value="USD" />
									<?php else : ?>
										<select name="amount">
											<option value="1"><?php _e('Pay a coffee - 1&euro;', 'wpcode-content-ratio'); ?></option>
											<option value="2"><?php _e('Pay a beer - 2&euro;', 'wpcode-content-ratio'); ?></option>
											<option value="3"><?php _e('Pay a snack - 3&euro;', 'wpcode-content-ratio'); ?></option>
											<option value="5"><?php _e('Pay a drink - 5&euro;', 'wpcode-content-ratio'); ?></option>
											<option value="10"><?php _e('Pay the cinema - 10&euro;', 'wpcode-content-ratio'); ?></option>
										</select><br />
										<input type="hidden" name="currency_code" value="EUR" />
									<?php endif; ?>
									<input type="image" src="<?php echo TSC_MEDIA_URI; ?>/img/<?php _e('donate_en.gif', 'wpcode-content-ratio'); ?>" name="submit" alt="<?php _e('PayPal - The safer, easier way to pay online.', 'wpcode-content-ratio'); ?>" width="92" height="26" style="width: auto;"/>
								</div>
								<h3><?php _e('You can follow me on', 'wpcode-content-ratio'); ?></h3>
								<p class="description">
					                <a href="http://jonatanjumbert.com/blog/?utm_source=Wordpress&utm_medium=Plugin&utm_term=WPCode%20Content%20Ratio&utm_campaign=Wordpress%20plugins" title="<?php _e('View Jonatan Jumbert\'s personal blog', 'wpcode-content-ratio'); ?>">
					                	<img src="<?php echo TSC_MEDIA_URI; ?>/img/blog.png" alt="<?php _e('Follow me on my blog', 'wpcode-content-ratio'); ?>" height="64" width="64">
					                </a>
					                <a href="https://plus.google.com/+JonatanJumbert" title="<?php _e('View Jonatan Jumbert\'s Google+ profile', 'wpcode-content-ratio'); ?>">
					                	<img src="<?php echo TSC_MEDIA_URI; ?>/img/googleplus.png" alt="<?php _e('Follow me on Google+', 'wpcode-content-ratio'); ?>" height="64" width="64">
					                </a>
					                <a href="http://www.linkedin.com/pub/jonatan-jumbert-avil%C3%A9s/14/540/443" title="<?php _e('View Jonatan Jumbert\'s Linkedin profile', 'wpcode-content-ratio'); ?>">
					                	<img src="<?php echo TSC_MEDIA_URI; ?>/img/linkedin.png" alt="<?php _e('Follow me on Linkedin', 'wpcode-content-ratio'); ?>" height="64" width="64">
					                </a>
					                <a href="http://twitter.com/jonatanjumbert" title="<?php _e('View Jonatan Jumbert\'s Twitter profile', 'wpcode-content-ratio'); ?>">
					                	<img src="<?php echo TSC_MEDIA_URI; ?>/img/twitter.png" alt="<?php _e('Follow me on Twitter', 'wpcode-content-ratio'); ?>" height="64" width="64">
					                </a>
					                <a href="https://www.facebook.com/jonatan.jumbert" title="<?php _e('View Jonatan Jumbert\'s Facebook profile', 'wpcode-content-ratio'); ?>">
					                	<img src="<?php echo TSC_MEDIA_URI; ?>/img/facebook.png" alt="<?php _e('Follow me on Facebook', 'wpcode-content-ratio'); ?>" height="64" width="64">
					                </a>
				               	</p>
							</div>
						</div>
				</div>
			</div>
		</div>
	</div><?php
}

function jja_save_wpcode_options($post_data) {
	$user_data = array();
	
	$user_data['options_checked'] = true;
	
	$user_data['calculate_on_listing'] = isset($post_data['calculate_on_listing']) ? true : false; 
	$user_data['calculate_categories'] = isset($post_data['calculate_categories']) ? true : false;
	$user_data['calculate_tags'] = isset($post_data['calculate_tags']) ? true : false;
	$user_data['calculate_authors'] = isset($post_data['calculate_authors']) ? true : false; 
	$user_data['calculate_on_save'] = isset($post_data['calculate_on_save']) ? true : false; 
	
	if(isset($post_data['calculate_taxonomies'])) {
		$data = array();
		foreach($post_data['calculate_taxonomies'] as $tax) {
			$data[$tax] = $tax;
		}
		$user_data['calculate_taxonomies'] = $data;
	} else {
		$user_data['calculate_taxonomies'] = array();
	}

	if(isset($post_data['calculate_post_types'])) {
		$data = array();
		foreach($post_data['calculate_post_types'] as $type) {
			$data[$type] = $type;
		}
		$user_data['calculate_post_types'] = $data;
	} else {
		$user_data['calculate_post_types'] = array();
	}

	update_option('wpcode_options', $user_data);
	global $plugin_options;
	$plugin_options = $user_data;
	
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __("Options for <strong>WPCode Content Ratio</strong> saved", 'wpcode-content-ratio'), '', '');
}

/**
 * Calculate all categories code to content ratio
 * @return unknown_type
 */
function jja_calc_all_categories() {
	$categories = get_categories();
	delete_option('wpcode_categories_ratio');
	
	$category_options = array();
	foreach($categories as $cat) {
		$ratio = jja_check_ratio(get_category_link($cat->term_id));
		$category_options[$cat->term_id] = $ratio;
		set_time_limit(60);
	}
	add_option('wpcode_categories_ratio', $category_options);
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __("All ratios for <strong>categories</strong> calculated.", 'wpcode-content-ratio'), '', '');
}

/**
 * Calculate code to content ratio for category id passed as argument
 * @param $category_id
 * @return unknown_type
 */
function jja_calc_category($category_id) {
	$ratio = jja_check_ratio(get_category_link($category_id));
	$category_options = get_option('wpcode_categories_ratio');
	if($category_options) {
		$category_options[$category_id] = $ratio;
		update_option('wpcode_categories_ratio', $category_options);
	} else {
		$categories = array($category_id => $ratio);
		add_option('wpcode_categories_ratio', $categories);
	}
	$cat = get_category($category_id);
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __("Ratio for <strong>%s</strong> category calculated.", 'wpcode-content-ratio'), $cat->name, '');
}

/**
 * Clean all code to content ratio for categories
 * @return unknown_type
 */
function jja_clean_categories() {
	delete_option('wpcode_categories_ratio');	
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __("All ratios for <strong>categories</strong> pages cleared.", 'wpcode-content-ratio'), '', '');
}

/**
 * Calculate all tags code to content ratio
 * @return unknown_type
 */
function jja_calc_all_tags() {
	$tags = get_tags();
	delete_option('wpcode_tags_ratio');
	
	$tag_options = array();
	foreach($tags as $tag) {
		$ratio = jja_check_ratio(get_tag_link($tag->term_id));
		$tag_options[$tag->term_id] = $ratio;
		set_time_limit(60);
	}
	add_option('wpcode_tags_ratio', $tag_options);
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __("All ratios for <strong>tags's</strong> page calculated.", 'wpcode-content-ratio'), '', '');
}

/**
 * Calculate code to content ratio for tag id passed as argument
 * @param $category_id
 * @return unknown_type
 */
function jja_calc_tag($tag_id) {
	$ratio = jja_check_ratio(get_tag_link($tag_id));
	$tag_options = get_option('wpcode_tags_ratio');
	if($tag_options) {
		$tag_options[$tag_id] = $ratio;
		update_option('wpcode_tags_ratio', $tag_options);
	} else {
		$tags = array($tag_id => $ratio);
		add_option('wpcode_tags_ratio', $tags);
	}
	$term = get_tag($tag_id);
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __("Ratio for <strong>%s</strong> tag page calculated.", 'wpcode-content-ratio'), $term->name,'');
}

/**
 * Clean all code to content ratio for tags
 * @return unknown_type
 */
function jja_clean_tags() {
	delete_option('wpcode_tags_ratio');	
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __("All ratios for <strong>tags's</strong> page cleared.", 'wpcode-content-ratio'), '', '');
}

/**
 * Calculate code to content ratio for post_type passed as argument
 * @param unknown_type $post_type
 */
function jja_calc_per_post_type($post_type) {
	global $wpdb;
	$entries = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $post_type));
	
	foreach($entries as $entry) {
		$ratio = jja_check_ratio(get_permalink($entry->ID));
		update_post_meta($entry->ID, 'ratio', $ratio);
		set_time_limit(60);
	}
	
	$posttype = get_post_type_object($post_type);
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __("All ratios for <strong>%s</strong> entries calculated.", 'wpcode-content-ratio'), $posttype->labels->name,'');
}

/**
 * Clean all ratios for post_type passed as argument
 * @param unknown_type $post_type
 */
function jja_clean_post_type($post_type) {
	global $wpdb;
	$prepared_st = $wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $post_type);
	$entries = $wpdb->get_results($prepared_st);
	foreach($entries as $entry) {
		delete_post_meta($entry->ID, 'ratio');
	}
	
	$posttype = get_post_type_object($post_type);
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __("All ratios for <strong>%s</strong> entries cleared.", 'wpcode-content-ratio'), $posttype->labels->name,'');
}

/**
 * Check content to code ratio for homepage
 */
function jja_calc_home_ratio() {
	global $plugin_options;

	$ratio = jja_check_ratio(get_bloginfo('wpurl'));
	$plugin_options['home_ratio'] = $ratio;
	update_option('wpcode_options', $plugin_options);
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __("Code to content ratio for <strong>homepage</strong> calculated.", 'wpcode-content-ratio'), '','');
}

/**
 * Clean all author's page ratios
 * @param unknown_type $post_type
 */
function jja_clean_authors() {
	delete_option('wpcode_author_ratio');
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __("All author's page ratio cleared.", 'wpcode-content-ratio'), '','');
}

/**
 * Calculate all code to content ratio for author's pages
 * @return unknown_type
 */
function jja_calc_all_authors() {
	$authors = get_users();
	delete_option('wpcode_author_ratio');
	
	$author_options = array();
	foreach($authors as $author) {
		$ratio = jja_check_ratio(get_author_posts_url($author->ID));
		$author_options[$author->ID] = $ratio;
		set_time_limit(60);
	}
	add_option('wpcode_author_ratio', $author_options);
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __("All author's page ratio calculated.", 'wpcode-content-ratio'), '','');
}

/**
 * Calculate code to content ratio for tag id passed as argument
 * @param $category_id
 * @return unknown_type
 */
function jja_calc_author($author_id) {
	$ratio = jja_check_ratio(get_author_posts_url($author_id));
	$author_options = get_option('wpcode_author_ratio');
	if($author_options) {
		$author_options[$author_id] = $ratio;
		update_option('wpcode_author_ratio', $author_options);
	} else {
		$author_option = array($author_id => $ratio);
		add_option('wpcode_author_ratio', $author_option);
	}
	$author = get_the_author_meta('display_name', $author_id);
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __('Author page ratio for user <strong>%s</strong> calculated.', 'wpcode-content-ratio'), $author,'');
}

/**
 * Calculate all code to content ratio form terms under taxonomy $taxonomy
 */
function jja_calc_all_taxonomies($taxonomy) {
	delete_option('wpcode_tax_'.$taxonomy.'_ratio');
	
	$terms = get_terms($taxonomy);
	$current_tax = get_taxonomy($taxonomy);
	
	$terms_taxonomies = array();
	foreach($terms as $term) {
		$ratio = jja_check_ratio(get_term_link($term));
		$terms_taxonomies[$term->term_id] = $ratio;
		set_time_limit(60);
	}
	add_option('wpcode_tax_'.$taxonomy.'_ratio', $terms_taxonomies);
	
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __('All terms ratio under <strong>%s</strong> taxonomy calculated.', 'wpcode-content-ratio'), $current_tax->label, '');
}


function jja_clean_taxonomies($taxonomy) {
	$current_tax = get_taxonomy($taxonomy);
	delete_option('wpcode_tax_'.$taxonomy.'_ratio');
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __('All terms ratio under <strong>%s</strong> taxonomy cleared.', 'wpcode-content-ratio'), $current_tax->label, '');
}

/**
 * Calculate code to content ratio for term taxonomy passed as argument
 * @param $taxonomy
 * @param $term_id
 * @return unknown_type
 */
function jja_calc_taxonomy_term($taxonomy, $term_id) {
	$current_tax = get_taxonomy($taxonomy);
	$term = get_term($term_id, $taxonomy);
	$ratio = jja_check_ratio(get_term_link($term));
	$taxonomy_options = get_option('wpcode_tax_'.$taxonomy.'_ratio');
	if($taxonomy_options) {
		$taxonomy_options[$term_id] = $ratio;
		update_option('wpcode_tax_'.$taxonomy.'_ratio', $taxonomy_options);
	} else {
		$taxs = array();
		$taxs[$term_id] = $ratio;
		add_option('wpcode_tax_'.$taxonomy.'_ratio', $taxs);
	}
	add_action('admin_notices', 'jja_notice_save', 10, 3);
	do_action('admin_notices', __('<strong>%s</strong> term under <i>%s</i> taxonomy ratio was updated.', 'wpcode-content-ratio'), $term->name, $current_tax->label);
} 


/*################################################################################################################################################*/
/**
 * Get HTML code from url passed by param
 * @param $url
 * @return unknown_type
 */
function jja_file_get_contents_curl($url) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_URL, $url);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}

/**
 * Strip HTML tags 
 * @param $text
 * @return unknown_type
 */
function jja_strip_html_tags($text) {
    $text = preg_replace(
        array(
            '@<head[^>]*?>.*?</head>@siu',
            '@<style[^>]*?>.*?</style>@siu',
            '@<script[^>]*?.*?</script>@siu',
            '@<object[^>]*?.*?</object>@siu',
            '@<embed[^>]*?.*?</embed>@siu',
            '@<applet[^>]*?.*?</applet>@siu',
            '@<noframes[^>]*?.*?</noframes>@siu',
            '@<noscript[^>]*?.*?</noscript>@siu',
            '@<noembed[^>]*?.*?</noembed>@siu',
            '@</?((address)|(blockquote)|(center)|(del))@iu',
            '@</?((div)|(h[1-9])|(ins)|(isindex)|(p)|(pre))@iu',
            '@</?((dir)|(dl)|(dt)|(dd)|(li)|(menu)|(ol)|(ul))@iu',
            '@</?((table)|(th)|(td)|(caption))@iu',
            '@</?((form)|(button)|(fieldset)|(legend)|(input))@iu',
            '@</?((label)|(select)|(optgroup)|(option)|(textarea))@iu',
            '@</?((frameset)|(frame)|(iframe))@iu',
        ),
        array(
            ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',
            "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0",
            "\n\$0", "\n\$0",
        ), $text);
    return strip_tags($text);
}

/**
 * Check code to content ratio for url passed by param.
 * @param $url
 * @return unknown_type
 */
function jja_check_ratio($url) {
	$real_content = jja_file_get_contents_curl($url);
	$content = jja_strip_html_tags($real_content);
	$content = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", " ", $content);
	$len_real = strlen($real_content);
	$len_strip = strlen($content);
	return round((($len_strip/$len_real)*100), 2);
}

/*################################################################################################################################################*/
add_filter('request', 'jja_ratio_column_orderby');
if(isset($plugin_options['calculate_post_types'])) {
	foreach($plugin_options['calculate_post_types'] as $post_type) {
		if(!in_array($post_type, $non_code_content_ratio_post_types)) {
			add_filter("manage_edit-{$post_type}_columns", 'jja_admin_post_header_columns', 10, 1);
			add_filter("manage_edit-{$post_type}_sortable_columns", 'jja_admin_post_sortable_columns', 10, 1);
			add_action("manage_{$post_type}s_custom_column", 'jja_admin_post_data_row', 10, 2);
		}
	}
}
/**
 * Sorting column by ratio value
 * @param $vars
 * @return unknown_type
 */
function jja_ratio_column_orderby($vars) {
	if(isset($vars['orderby']) && 'ratio' == $vars['orderby']) {
		$vars = array_merge($vars, array('meta_key' => 'ratio', 'orderby' => 'meta_value_num'));
	}
	return $vars;
}

/**
 * Custom ratio column
 * @param $columns
 * @return unknown_type
 */
function jja_admin_post_header_columns($columns) {
    if(!isset($columns['ratio'])) $columns['ratio'] = "ratio";
    return $columns;
}

/**
 * Making ratio column sortable
 * @param $columns
 * @return unknown_type
 */
function jja_admin_post_sortable_columns($columns) {
	$custom = array('ratio' => 'ratio');
	return wp_parse_args($custom, $columns);
}

/**
 * Display code to content ratio in column
 * @param $column_name
 * @param $post_id
 * @return unknown_type
 */
function jja_admin_post_data_row($column_name, $post_id) {
    switch($column_name) {
        case 'ratio': 
        	$ratio = get_post_meta($post_id, __('ratio', 'wpcode-content-ratio'), true);
        	$plugin_options = get_option('wpcode_options');
        	if(empty($ratio) && $plugin_options['calculate_on_listing'] == true) {
            	$ratio = jja_check_ratio(get_permalink($post_id));
            	update_post_meta($post_id, 'ratio', $ratio);
            	echo "<span class='row-title' ".jja_determine_color($ratio).">".number_format($ratio,2)." %</span>";
        	} else {
        		if(empty($ratio)) {
        			_e('Empty', 'wpcode-content-ratio');
        		} else echo "<span class='row-title' ".jja_determine_color($ratio).">".number_format($ratio,2)." %</span>";
        	}
        	set_time_limit(60);
            break;
        default:
            break;
    }
}

/**
 * Show ratio in colors for determine if url is good enough
 * RED : Bad ratio, unnder 10%
 * BLUE : Not good enough, between 10 and 25 % or over 70%
 * GREEN: Good ratio, between 25 and 70%.
 * @param $ratio
 * @return unknown_type
 */
function jja_determine_color($ratio) {
	$ratio = floatval($ratio);
	if($ratio < 10) return 'style="color:red"';
	if($ratio >= 10 && $ratio < 25) return "";
	if($ratio >= 25 && $ratio <= 70) return 'style="color:green"';
	if($ratio > 70) return "";
}

/*################################################################################################################################################*/
add_action('save_post', 'jja_calculate_content_code_ratio');

/**
 * After save a post check code to content ratio. 
 * @param $post_id
 * @return unknown_type
 */
function jja_calculate_content_code_ratio($post_id) {
	global $plugin_options;
	if($plugin_options['calculate_on_save'] == true) {
		if(!wp_is_post_revision($post_id)) {
			$ratio = jja_check_ratio(get_permalink($post_id));
			update_post_meta($post_id, 'ratio', $ratio);
		}
	}
}

/*###################################################################################################################################################*/
function jja_setup_plugin_notice() { ?> 
    <div class="error">
		<p>
			<?php _e('You must configure <strong>WPCode Content Plugin</strong>.', 'wpcode-content-ratio'); ?>&nbsp;
			<a href="<?php echo get_bloginfo('wpurl');?>/wp-admin/admin.php?page=wpcode-content-ratio" title="<?php _e('Configure', 'wpcode-content-ratio');?>">
				<?php _e("Let's do it!", 'wpcode-content-ratio'); ?>
			</a>
		</p>
    </div><?php
}

function jja_notice_save($message, $var_name = '', $var2_name = '') { ?> 
    <div class="updated">
		<p><?php
			if(!empty($var_name)) {
				if(!empty($var2_name)) {
					printf($message, $var_name, $var2_name);
				} else {
					printf($message, $var_name);
				}
			} else echo $message; ?>
		</p>
    </div><?php
}

global $plugin_options;
if(isset($plugin_options['options_checked']) && $plugin_options['options_checked'] == false) {
	add_action('admin_notices', 'jja_setup_plugin_notice');
}
?>