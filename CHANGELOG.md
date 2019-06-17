# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- `processRow` method to alter a row directly before it is inserted or
updated. Useful for setting columns that aren't in the property map.

## [2.0.0] Pressurized Arrangement - 2019-03-22
### Added
- `$sort` parameter to `getEntitiesByIds()`.

### Changed
- By default results are now ordered by the ID field.
- `$fields` is now the second parameter of `getEntities()` instead of
the last.

### Removed
- Previously deprecated `getBaseSelect()`, `getIdColumn()`, `getSelectId()`,
and `rowsToJson()` methods.

## [1.2.2] Pasteurized Recognition - 2019-03-04
### Fixed
- `getById` route handler now respects `fields` parameter and only selects
the specified properties.

## [1.2.1] Reliant Progenitor - 2019-02-24
### Added
- Support for specifying dependent fields along with `getValue` function.
Dependents of requested fields that weren't explicitly requested, or
aren't default, will still be selected but not output.

### Fixed
- Properly check `nullGroup` properties nested multiple levels deep.
For example, when selecting the field `a.b.c`, if `a` is a nullable
group it will now be checked and set to null as expected. Previously
only the direct parent of a selected field was checked.

## [1.2.0] Emancipation Propagation - 2019-02-22
### Added
- Default and maximum limit can now be configured for each search route.
If not configured, these are set to 25 and 1000, respectively.
- `getBaseQuery()` method, which has an `$options` parameter that an
instance of `QueryOptions` is passed to. This makes it easy to only
select specified field columns, and optionally customize the query based
on which fields are selected or particular filter/sort values.
- `getPropMap()` method, which allows merging additional property info
with `getSelectMap()`. For example, a type or value getter function can
be specified to determine how the property is output.
- Optional `fields` parameter on `getEntityById()`, `getEntitiesByIds()`,
and `getEntities()` methods. Takes an array of strings specifying which
fields to select. If left empty all default properties will be selected.

### Deprecated
- `getBaseSelect()` - use `getBaseQuery()` instead.
- `rowsToJson()` - mapped property information is now used to
automatically output selected fields.
- `getIdColumn()` and `getSelectId()` - set an `id` property in
`getSelectMap()` or `getPropMap()` instead. If the select query uses a
different ID column name than the table used for inserts/updates/deletes,
override the table's ID column name by setting an `id` property in `getMap()`.

## [1.1.1] Maximal Limitation - 2019-01-16
### Fixed
- Error when requesting the maximum page size of 1000.

### Changed
- Upgraded peachy-sql dependency to v6.0.

## [1.1.0] Ambiguous Identity - 2019-01-11
### Added
- `getSelectId()` method to optionally override the column used to
get entities by ID. Necessary when a joined table has a column with
the same name as the ID column.
- Return `offset`, `limit`, and `lastPage` properties from search
route handler. This makes it easy for API clients to see if there are
more results to request.

## [1.0.2] Optimal Fixture - 2017-05-16
### Changed
- Methods for retrieving, patching, and deleting entities by IDs now
return early if passed an empty IDs array.

## [1.0.1] Exacting Characteristic - 2017-03-14
### Changed
- `RouteHandler` now ensures that search parameters have the correct type.

## [1.0.0] Cosmic Luminary - 2017-03-09
### Changed
- Initial stable release

[Unreleased]: https://github.com/theodorejb/phaster/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/theodorejb/phaster/compare/v1.2.2...v2.0.0
[1.2.2]: https://github.com/theodorejb/phaster/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/theodorejb/phaster/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/theodorejb/phaster/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/theodorejb/phaster/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/theodorejb/phaster/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/theodorejb/phaster/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/theodorejb/phaster/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/theodorejb/phaster/tree/v1.0.0
