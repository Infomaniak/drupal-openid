# INTRODUCTION

The Infomaniak Connect module provides an Infomaniak Auth login provider client 
for the OpenID Connect module.

## FEATURES
* Preconfigured client using auto-discovered endpoints.
* Log in to Drupal using Infomaniak Auth 2.0 via OpenID Connect.
* Synchronize email address changes from Infomaniak with the connected Drupal user account.
* Restrict login access to specific email addresses.
* Grant administrative access only to specific email addresses upon login.

## REQUIREMENTS

* Drupal OpenID Connect module - https://www.drupal.org/project/openid_connect

## INSTALLATION

Install as you would normally install a contributed Drupal module.
See: https://www.drupal.org/node/895232 for further information.

## CONFIGURATION

* Install the module and all its dependencies as you would do with any other
  Drupal module.
  If you install using composer, the openid_connect will be installed
  automatically: `composer require drupal/infomaniak_connect`
* Enable the module.
* Go to the openid_connect settings at `Administration / Configuration / People / OpenID Connect`
* Infomaniak OAuth 2.0 will be available as a client and preconfigured.
* Create an Auth application on the Infomaniak Manager https://manager.infomaniak.com/v3/ng/profile/user/applications/list to fetch the Client ID and Client secret. 
* Specify the redirect URI provided at the end of the OpenID Connect client settings page
* Change the "OpenID buttons display in user login form" settings to display the Login with Infomaniak button for your users

## MAINTAINERS

Current maintainers:

 * Infomaniak - https://www.infomaniak.com/

