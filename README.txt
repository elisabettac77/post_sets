Plugin Name:  Post Sets Manager
Description:  Manages post sets with featured images and episode tracking.
Version:      1.0
Author: Elisabetta Carrara Author URI:   https://elica-webservices.it/
Plugin URI:   https://elica-webservices.it/
Text Domain:  post-sets
Requires PHP: 8.0
Requires CP:  2.0

 
Post Sets Manager ClassicPress Plugin Documentation

Welcome to the Post Sets plugin! This page explains how the plugin works and how you can customize it.

How It Works

The plugin creates a custom taxonomy called post_set that allows you to group posts into sets. This taxonomy behaves like a tag and allows you to upload a featured image to identify it. It also provides a custom archive template to display all posts in a selected post set, ordered by a custom meta field episode_number and shows a subtitle under a single post title to show episode number and a link to the post set.
Using and Customizing the Archive Template

To use the template (located in the templates/taxonomy-post_set.php file), copy it to your theme root folder. That way, in case the plugin gets updated it is not overwritten. The specific template included into the plugin is adapted to work with MH Magazine Lite Theme. To make it work with the theme you are using on your website, you need to:

- Make a copy of the archive.php template in your theme.
- Rename it to taxonomy-post_set.php.
- Include the custom functionalities of the taxonomy-post_set.php archive you find in the plugin templates folder into it.

(You can ask an AI to do this adaptation to the archive template for you if you are not confident to do it yourself.) Keep in mind the theme first checks if there is a specific template that caters to the Post Set taxonomy inside itself, and then falls back to the templates folder of the plugin if it cannot find a suitable archive template.

The template uses a custom query to fetch posts in the current post set, ordered by the episode_number meta key. You can modify the query or the HTML markup to better suit your needs.

You can also use the shortcode [post_sets_menu] on any post, page, or template (if on a template you will have to use the do_shortcode() function to make it work) in your site to show a list of Post Sets.

If you have any questions or suggestions, feel free to open an issue on the plugin’s repository.
