# Changelog

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
