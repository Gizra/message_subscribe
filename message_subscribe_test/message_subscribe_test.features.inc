<?php
/**
 * @file
 * message_subscribe_test.features.inc
 */

/**
 * Implements hook_ctools_plugin_api().
 */
function message_subscribe_test_ctools_plugin_api($module = NULL, $api = NULL) {
  if ($module == "strongarm" && $api == "strongarm") {
    return array("version" => "1");
  }
}

/**
 * Implements hook_default_message_type().
 */
function message_subscribe_test_default_message_type() {
  $items = array();
  $items['message_subscribe_example_og_post_in_group'] = entity_import('message_type', '{
    "name" : "message_subscribe_example_og_post_in_group",
    "description" : "Message subscribe example - OG post in group",
    "argument_keys" : [ "!groups" ],
    "argument" : [],
    "category" : "message_type",
    "data" : {
      "token options" : { "clear" : 0 },
      "purge" : { "override" : 0, "enabled" : 0, "quota" : "", "days" : "" }
    },
    "language" : "en",
    "arguments" : null,
    "message_text" : { "und" : [
        {
          "value" : "[message:user:picture]",
          "format" : "filtered_html",
          "safe_value" : "\\u003Cp\\u003E[message:user:picture]\\u003C\\/p\\u003E\\n"
        },
        {
          "value" : "\\u003Ca href=\\u0022[message:user:url]\\u0022\\u003E[message:user:name]\\u003C\\/a\\u003E posted \\u003Ca href=\\u0022[message:field-node-ref:url]\\u0022\\u003E[message:field-node-ref:title]\\u003C\\/a\\u003E in !groups.",
          "format" : "full_html",
          "safe_value" : "\\u003Cp\\u003E\\u003Ca href=\\u0022[message:user:url]\\u0022\\u003E[message:user:name]\\u003C\\/a\\u003E posted \\u003Ca href=\\u0022[message:field-node-ref:url]\\u0022\\u003E[message:field-node-ref:title]\\u003C\\/a\\u003E in !groups.\\u003C\\/p\\u003E\\n"
        },
        {
          "value" : "[message:field-node-ref:node-teaser]",
          "format" : "filtered_html",
          "safe_value" : "\\u003Cp\\u003E[message:field-node-ref:node-teaser]\\u003C\\/p\\u003E\\n"
        }
      ]
    },
    "rdf_mapping" : []
  }');
  return $items;
}
