# Plugin: Company Segments by Leuchtfeuer



## Overview

This plugin brings company-based Segments to Mautic.

It is part of the "ABM" suite of plugins that extends Mautic capabilities for working with Companies.

## Requirements for this release
> [!TIP]
> Other releases of this plugin may cover different Mautic versions!
- Mautic 6

## Installation
### Composer
This plugin can be installed through composer.

### Manual install
Alternatively, it can be installed manually, following the usual steps:

* Download the plugin
* Unzip to the Mautic `plugins` directory
* Rename folder to `LeuchtfeuerCompanySegmentsBundle` 

-
* In the Mautic backend, go to the `Plugins` page as an administrator
* Click on the `Install/Upgrade Plugins` button to install the Plugin.

OR

* If you have shell access, execute `php bin\console cache:clear` and `php bin\console mautic:plugins:reload` to install the plugins.

## Plugin Activation and Configuration
1. Go to `Plugins` page
2. Click on the `Company Segments` plugin
3. ENABLE the plugin

## Usage
The plugin brings a new menu item `Companies -> Company Segments`. Here you can create and manage your Company Segments, and see the number of Companies are in each Segment. When you click on that number, you go to a pre-filtered Company list view.

You can also filter manually for Company Segments in the Company list view. Just enter `segment:<company segment alias name>` in the filter.

In the Company single view, you will now find a green bubble for each of the company's tag, on the right hand side. Click the bubble to remove the tag from the company with one click.

In the Company edit view, you can add or remove tags as desired.

In Campaigns, you now have a new Actions called:
* "Modify Company Tags"
* "Modify Company Segments".

In Campaigns, you now have a new Condition called "Company Segments"

In Reports, you now have a new data source "Company Segments" that allows you to filter on Company Segments.

To update the Company Segments based on their filter, there is a console command as cron job: `php bin/console leuchtfeuer:abm:segments-update`. It works just like with lead segments.

Event Log is created for each Segment updated in the Company Segments view.

Audit log is created for each Company Segment created, updated or deleted.

Placeholder contacts: If a company is created, a "placeholder" contact is created with the same contact data (first name = companyname, last name = "[PLACEHOLDER]", postal address = company’s postal address (multiple fields), email = company email, phone = company phone, fax = company fax. And of course the relationship placeholder <-> company). This can be used to contact and handle a company using Mautic campaigns. When company data is changed, that will reflect to the placeholder contact. Changes directly to the placeholder contact are not supported and will be overwritten.
The Placeholder Contacts feature can be disabled in the plugin configuration.

## API

Standard CRUD endpoints are available for managing Company Segments:

```
GET /api/companysegments                                                                                 
GET /api/companysegments/{id}                                                                            
POST /api/companysegments/new                                                                             
POST /api/companysegments/batch/new                                                                       
PUT /api/companysegments/batch/edit                                                                      
PATCH /api/companysegments/batch/edit                                                                      
PUT /api/companysegments/{id}/edit                                                                       
PATCH /api/companysegments/{id}/edit                                                                       
DELETE /api/companysegments/batch/delete                                                                    
DELETE /api/companysegments/{id}/delete
```

## Troubleshooting
Make sure you have not only installed but also enabled the Plugin.

If things are still funny, please try

`php bin/console cache:clear`

and 

`php bin/console mautic:assets:generate`

## Known Issues
* MAJOR: The manual mofication of Company Segment membership is prctically  broken, because it is overwritten by the console command.
* In the Company Segements view, the number of Companies are in each Segment is currently not calculated correctly.
* In the Company Segements view, the "select all" checkbox does not work properly.
* An exception occurs when cancelling the creation of a new Segment

## Future Ideas
* MAYBE: Manual modification of Segments from Company list view without bulk action
* Reflect placeholder contact changes in audit log and timeline
* Company / CompanySegment permissions

## Credits
* @biozshock
* @ekkeguembel
* @JonasLudwig1998
* @lenonleite
* @LeonOltmanns
* @MadlenF
* @PatrickJenkner
* @patrykgruszka

## Author and Contact
Leuchtfeuer Digital Marketing GmbH

Please raise any issues in GitHub.

For all other things, please email mautic-plugins@Leuchtfeuer.com
