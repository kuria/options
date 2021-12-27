Changelog
#########

2.0.1
*****

- PHP 8.1 compatibility


2.0.0
*****

- added support for node option normalizers
- added ``$context`` argument to ``Resolver::resolve()``
- added ``Resolver::addNormalizer()``, ``Resolver::addValidator()``
- removed ``Resolver::removeOption()``, ``Resolver::clearOptions()``
- renamed ``OptionFactory`` to ``Option``
- validator may now return return error messages as strings
- leaf option normalizers are now correctly called before validators


1.0.0
*****

Initial release
