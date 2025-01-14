# Changelog

## 1.7.2 (2025-01-14)

### What's fixed
* Fixed taxonomy terms not being imported correctly #83 by @duncanmcclean



## 1.7.1 (2025-01-09)

### What's fixed
* Fixed formatting issues when importing Classic Editor content from WordPress #81 by @duncanmcclean
* Reverted "Fix import issue with Bard" #79 by @duncanmcclean



## 1.7.0 (2025-01-07)

### What's new
* Added support for the List fieldtype #75 by @carstenjaksch
* Added support for importing date ranges #78 by @duncanmcclean
* Added support for importing alt text for multiple assets at once #74 by @carstenjaksch
* Now, only the Bard buttons used by your content will be enabled #77 by @duncanmcclean
* Added note to the docs about importing multiple values #70 by @carstenjaksch

### What's fixed
* Fixed existing assets check when using the "Folder" option #73 by @carstenjaksch



## 1.6.0 (2024-12-20)

### What's new
- Multi-site taxonomy support #67 by @duncanmcclean
- Added "Blueprint" option when configuring entry & term imports #64 by @duncanmcclean
- Downloaded assets can now be processed using your asset container's source preset #59 by @duncanmcclean
- Added a progress indicator when saving / running an import by @duncanmcclean
- Added a tooltip when hovering over a field name in the mappings table by @duncanmcclean
- The "Unique Field" option has been removed from term & user imports. The Slug and Email will be used instead #66 by @duncanmcclean

### What's fixed
- Fixed import issue with Bard #65 by @duncanmcclean



## 1.5.1 (2024-12-10)

### What's fixed
* Fixed error when importing structured collection without mapping "Parent" field #58 by @duncanmcclean
* A banner will now be displayed when a database hasn't been configured #56 by @duncanmcclean



## 1.5.0 (2024-11-29)

### What's new
* You can now import asset alt text #50 by @duncanmcclean
* The current file is now shown in the edit form #47 by @duncanmcclean
* PHP 8.4 Support #41 by @duncanmcclean

### What's fixed
* Null values are now filtered out when saving mappings #48 by @duncanmcclean
* Bumped `cross-spawn` from 7.0.3 to 7.0.6 #46 by @dependabot[bot]
* Fixed error when updating Bard configs inside fieldsets #22 by @duncanmcclean



## 1.4.1 (2024-11-21)

### What's fixed
* Fixed error when saving XML import #39 by @duncanmcclean



## 1.4.0 (2024-11-18)

### What's new
* Added support for Dictionary fields #37 by @duncanmcclean



## 1.3.0 (2024-11-12)

### What's new
* Allow selecting a folder for downloaded assets #31 by @duncanmcclean
* Made more strings translatable and fixed issue when string matches name of lang file #33 by @duncanmcclean
* JSON arrays now get parsed when importing relationships #32 by @duncanmcclean



## 1.2.0 (2024-11-08)

### What's new
* UX Improvements #24 by @duncanmcclean
* Added "CSV Delimiter" option #29 by @duncanmcclean
* You can now import publish states (& toggle fields) #30 by @duncanmcclean



## 1.1.2 (2024-11-06)

### What's fixed
* Fixed validation error when creating an import in a multi-site #27 by @duncanmcclean



## 1.1.1 (2024-11-04)

### What's fixed
* Fixed error when importing a Gutenberg block without content by @duncanmcclean



## 1.1.0 (2024-11-01)

### What's new
* Multi-site Support #13 by @duncanmcclean
* Entry parents can now be imported #20 by @duncanmcclean
* Import jobs are now processed in batches #16 by @duncanmcclean
* Transformers now have access to the `Import` object by @duncanmcclean
* Added a `messages` translation file for longer strings by @duncanmcclean
* You can now determine whether items should be created or updated when creating an import #11 by @duncanmcclean

### What's fixed
* Fixed `Unexpected data found` error when importing dates by @duncanmcclean
* Fix incorrect namespaces in tests by @duncanmcclean



## 1.0.2 (2024-10-28)

### What's fixed
* WordPress: `attachment` posts are now filtered out #7 by @duncanmcclean
* Gutenberg: Handle cases where `core/video` block might not be a `<video>` by @duncanmcclean
* Refactored how assets are handled in Bard fields #6 by @duncanmcclean
* Fixed confirmation modal not closing after deleting an import by @duncanmcclean
* Bumped `axios` from 0.21.4 to 0.28.0 #3 by @dependabot



## 1.0.1 (2024-10-28)

### What's fixed
* Fixed release workflow by @duncanmcclean



## 1.0.0 (2024-10-28)

Initial release! 🚀
