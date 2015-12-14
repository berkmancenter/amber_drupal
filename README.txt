INTRODUCTION
------------

Whether links fail because of DDoS attacks, censorship, or just plain old link rot, reliably accessing linked content is a problem for Internet users everywhere. The more routes we provide to information, the more all people can freely share that information, even in the face of filtering or blockages. Amber adds to these routes.

Amber automatically preserves a snapshot of every page linked to on a website, giving visitors a fallback option if links become inaccessible. If one of the pages linked to on this website were to ever go down, Amber can provide visitors with access to an alternate version. This safeguards the promise of the URL: that information placed online can remain there, even amidst network or endpoint disruptions.

Amber is an open source project led by the Berkman Center for Internet & Society. It builds on a proposal from Tim Berners-Lee and Jonathan Zittrain for a "mutual aid treaty for the Internet" that would enable operators of websites to enter easily into mutually beneficial agreements and bolster the robustness of the entire web. The project also aims to mitigate risks associated with increasing centralization of online content.

* For a full description of the module, visit the project page:
  http://amberlink.org/

* To submit bug reports and feature suggestions, or to track changes:
  https://drupal.org/project/issues/amber

REQUIREMENTS
------------

* cURL - To see if cURL is installed, go to Administration > Reports > Status Report.

RECOMMENDED MODULES
-------------------

The Libraries module (https://drupal.org/project/libraries) and the AWS PHP library (https://github.com/aws/aws-sdk-php) are required if using AWS to store snapshots.

INSTALLATION
------------

* Install as you would normally install a contributed Drupal module. 
* If using AWS to store snapshots, download version 3 of the AWS library from https://github.com/aws/aws-sdk-php and save it in sites/all/libraries/aws

CONFIGURATION
-------------

* Configure this module at Administration » Content Authoring » Amber
* View the snapshots preserved by Amber at Administration » Reports » Amber Dashboard

MAINTAINERS
-----------

Current maintainers:
 * Jeffrey Licht (jlicht)

This project has been sponsored by:
 * Berkman Center for Internet & Society (http://cyber.law.harvard.edu)

