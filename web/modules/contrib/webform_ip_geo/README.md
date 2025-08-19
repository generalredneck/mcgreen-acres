# Webform IP Geo

Webform IP Geo provides a simple way to extract geo data from the
IP of a webform submission.

The module provides a new "hidden" field to be added to webforms.
Upon submission of the webform, the module will use the submission
IP to get geo data via a 3rd party API. After that it will replace
the tokens in the webform field with the retrieved data.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/webform_ip_geo).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/webform_ip_geo).


# Required Modules

- [Webform](https://www.drupal.org/project/webform)


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).
  
for further information.
- The module will only work if an API provider module/plugin is enabled as well.
  This module has a submodule for the `https://ipapi.co` API


# Configuration

1. The configuration form is at `/admin/config/services/webform_ip_geo`
2. The debug-option allows to hardcode an IP to be used. This is helpful for
   local development environments that have a reserved IP for which the geo APIs
   will not be able to return any data.

**Adding custom provider**

1. New providers can easily be added by copying the code of the submodule
  `webform_ip_geo_ipapi_co` into a new module and to adjust the provider URL as
   well as the mapping implementation.
2. If more customization is needed, see `/src/Plugin/WebfomrIpGeoPluginBase.php`


## Maintainers

- Benjamin Koether - [bkoether](https://www.drupal.org/u/bkoether)
- Philippe Joulot - [phjou](https://www.drupal.org/u/phjou)
