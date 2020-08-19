# Garden Gnome Package

WordPress plugin to display panoramas, virtual tours or object movies created with [Pano2VR](https://ggnome.com/pano2vr) and [Object2VR](https://ggnome.com/object2vr).

https://wordpress.org/plugins/garden-gnome-package/

## Description

This plugin provides an easy way to publish panoramas and object movies created with Garden Gnome Software's Pano2VR and Object2VR.

You can embed a package via a shortcode like `[ggpkg id=12]` or a block in Elementor or the Gutenberg editor.

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

