# Wisc H5P LTI Outcomes

A plugin created by UW-Madison to retrieve H5P statements from a Learning Locker LRS to send back to the LMS as grades. 

## Requirements

### H5P xAPI

<https://github.com/tunapanda/wp-h5p-xapi>

Must have the wp-h5p-xapi plugin installed. Uses the same endpoints as this plugin to retrieve the H5P statements.

### Wisc Pressbooks LTI

<https://github.com/TRAD-UWMadison/wiscpb-lti>

This plugin uses the outcomes saved from the LTI launch in the Wisc Pressbooks LTI plugin which also must be installed.

### Hypothesis 

<https://wordpress.org/plugins/hypothesis/>, <https://github.com/hypothesis/wp-hypothesis>

The hypothesis plugin adds annotations to a WordPress site.  This plugin will check to see if the "hypothesis" script is enqueued, and will replace it with a custom built (by the Hypothesis team) boot script to enable embedded content within annotations.

To confirm the integrity of the remotely hosted Hypothesis boot script, navigate to the tests directory and run the following validation script.

```
./validate.sh
```

