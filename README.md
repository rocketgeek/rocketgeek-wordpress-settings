RocketGeek WordPress Settings Framework
============================

The RocketGeek WordPress Settings Framework is an object class for quickly constructing admin panel settings 
used in WordPress plugins and themes. The objective is fast deployment made simple by a basic settings array 
as the only requirement.

It is based on the WordPress settings framework by Gilbert Pellegram and James Kemp, framework version 1.6.9. 
The original class was good for a starting point, but I needed additional elements - a more flexible hooks structure
along with more hooks overall, and the ability to package it as a library in multiple plugins that all might be 
loaded together (so it needed to avoid naming collisions). The original version I worked from did not meet those
requirements, nor did it escape untrusted output (although the original framework now seems to have now added these).

I have merged in changes to the original framework with my version, so my key differences are:
* Minified CSS/JS files
* Custom versions of WP's do_settings_sections() and do_settings_fields() functions so we can add more hooks.
* More hooks.
* Simplified hook naming convention
* An API file with externally callable functions.

Hooks
----------------------

There are a number of filter and action hooks throughout the class to allow for customizing.  The prefix for
all of them is the prefix used for the option group.  Some also use the section ID and/or field ID to allow
for more precision.

**Filters**
* <option_group>_register_settings
* <option_group>_menu_icon_url
* <option_group>_menu_position
* <option_group>_settings_page_title
* <option_group>_settings_validate
* <option_group>_settings_defaults
* <option_group>_field_description
* <option_group>_show_save_changes_button
* <option_group>_show_tab_links
* <option_group>_<section_id>_after_tab_links
* <option_group>_settings_section_fields
* <option-group>_<section_id>_settings_section_fields

**Actions**
* <option_group>_settings_page_after_title
* <option_group>_before_field
* <option_group>_before_field_<field_id>
* <option_group>_after_field
* <option_group>_after_field_<field_id>
* <option_group>_before_settings
* <option_group>_before_settings_fields
* <option_group>_do_settings_sections
* <option_group>_after_settings
* <option_group>_<tab_id>_before_settings_section
* <option_group>_<tab_id>_after_settings_section
* <option_group>_before_tab_links
* <option_group>_after_tab_links
* <option_group>_<section_id>_before_h2
* <option_group>_<section_id>_after_h2
* <option_group>_<section_id>_settings_section_before_field
* <option_group>_<section_id>_settings_section_before_field_key
* <option_group>_<section_id>_settings_section_after_field_key
* <option_group>_<section_id>_settings_section_after_field
