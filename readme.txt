# Garden Gnome Package
Contributors: Ggnomes
Tags: panorama, pano, virtual tour, webvr, pano2vr, object2vr
Requires at least: 5.0
Tested up to: 5.9.0
Stable tag: trunk
Requires PHP: 5.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display panoramas, virtual tours or object movies created with Pano2VR and Object2VR.

## Description

This plugin provides an easy way to publish panoramas and object movies created with Garden Gnome Software's Pano2VR and Object2VR.

You can embed a package via a shortcode like `[ggpkg id=12]` or a block in the Gutenberg editor.

Sample packages can be downloaded from our [forum](https://forum.ggnome.com/viewtopic.php?f=21&t=9025).

### Shortcode

When you are using a shortcode to embed a package, you can provide additional parameters in the shortcode:

- width: the width of the player in the page

- height: the height of the player in the page

- start_preview: when set to 'true', the player will initially show as a preview image with a play button.

- start_node: if the package is a virtual tour, you can specify the start node. You can find the node ID of each node in the tooltip in the tour browser.

- start_view: for panoramas and virtual tours, sets the initial view of the first node. The format is 'pan/tilt/fov/projection'. The projection parameter is optional.

- url: can be used instead of ID, to embed a package from a specific URL. Like `[ggpkg url='....']`

Example: `[ggpkg id=12 width='100%' height='500px' start_preview='true']`

If you are using the Gutenberg Editor and want to embed a package via a shortcode, use a *Classic Block* from the 'Formatting' section, and use the *Add Media* button to add a package from the media library.

### Gutenberg Block

You can find the GGPKG Gutenberg Block in the Widgets section.

In the GGPKG Block, you can pick a package from the media library.

In the Inspector panel on the right, you can specify if the package should start with a preview image and a play button, and set the width and height of the player in the page.

### Elementor Widget

You can find the Garden Gnome Package Widget in the General section.

In the Widget settings, you can pick a package from the media library, define the height, and select if it should start with a preview image.

## Installation

###Requirements: 

The [zip](https://www.php.net/manual/en/book.zip.php) and [libxml](https://www.php.net/manual/en/book.libxml.php) PHP module must be installed on your server.

### Installation:
1. Upload the plugin files to the `/wp-content/plugins/garden-gnome-package` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress

##  Frequently Asked Questions

### What is a Garden Gnome Package?

A Garden Gnome Package is a simple ZIP file that contains everything necessary to display a single panorama, virtual tour, or object movie. After uploading the package, the plugin extracts the archive.

### How can I create a package? 

Please see the [Pano2VR documentation](https://ggnome.com/doc/pano2vr/6/cms-plugins/).

### How can I upload a tour with a large file size? 

The easiest solution is to install the excellent [Tuxedo Big File Uploads](https://wordpress.org/plugins/tuxedo-big-file-uploads/) plugin.

There are two strategies without an additional plugin:

- Upload the tour to a folder on a web server and use the shortcode `[ggpkg url="https://example.com/my_tour_folder/"]` to point to the tour.

- Upload a small version of the tour (i.e., just the start node) and then replace the files in the extracted folder in the upload directory.

### How can contribute? 

Please submit a pull request on [GitHub](https://github.com/ggnomes/garden-gnome-package).

## Screenshots

1. Embedded virtual tour
2. Gutenberg block
3. Settings page
4. Shortcode in classic editor

## Changelog

### 2.2.5
* Fix for Elementor icon and version bump

### 2.2.4
* Fix for centered preview button

### 2.2.3
* Fix warnings in JSON parser

### 2.2.2
* Forces CSS line-height to 1.0 in skins

### 2.2.2
* Forces CSS line-height to 1.0 in skins

### 2.2.1
* Fix for uninstall hook and deprecation warning

### 2.2.0
* New icon
* Added WebXR support

### 2.1.3
* Fix for copy current package player
* Fix for multiple different skins on a page
* Fix in Gutenberg editor for WordPress 5.4

### 2.1.2
* Elementor widget is now responsive
* Fix for fullscreen, if the fullscreen API is missing

### 2.1.1
* Disable 'sslverify' for gginfo download, as this causes issues with PHP 7.4

### 2.1.0
* Added and Elementor widget
* Added a filter for packages in the media library
* Changed div container ids

### 2.0.1
* Improved CSS reset for images 

### 2.0
* Complete rewrite of the ggpkg-import plugin.



## Upgrade Notice

### 1.x
Please deactivate the old GGPKG-Import plugin to avoid conflicts