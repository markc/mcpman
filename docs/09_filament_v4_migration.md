# Filament v3 vs v4 Migration Guide

## Overview

⚠️ **BETA STATUS WARNING**: Filament v4 is currently in beta (v4.0.0-beta2, released June 10, 2025). **NOT SUITABLE FOR PRODUCTION USE** - breaking changes may still occur during the beta period. No stable release date has been announced.

🚨 **BETA IMPLEMENTATION NOTE**: This guide documents the **actual implementation** in Filament v4.0-beta2, which may differ from the official documentation as the framework is still in beta. Some features documented officially may not yet be implemented in this beta version.

This document provides comprehensive insights into the architectural changes and migration considerations when upgrading from Filament v3 to v4, based on hands-on experience with Filament v4.0-beta2 and the official upgrade guide.

## Critical Framework Changes

### Method Signatures (CHANGED in Beta2)

**🚨 BETA IMPLEMENTATION**: In Filament v4.0-beta2, method signatures have changed to Schema-based:

```php
// ❌ v3 Pattern (no longer works in beta2)
public static function form(Form $form): Form
{
    return $form->schema([
        // components here
    ]);
}

// ✅ v4 Beta2 Pattern (current implementation)
public static function form(Schema $schema): Schema
{
    return $schema->components([
        // components here
    ]);
}

// ✅ Table methods remain unchanged
public static function table(Table $table): Table
{
    return $table
        ->columns([
            // columns here
        ]);
}
```

**Note**: The official documentation suggests `form(Form $form): Form` should remain unchanged, but the beta2 implementation actually requires `form(Schema $schema): Schema`.

### Core Import Patterns (CHANGED in Beta2)

**🚨 BETA IMPLEMENTATION**: Core form imports have changed in beta2:

```php
// ❌ v3 Imports (not working in beta2)
use Filament\Forms\Form;

// ✅ v4 Beta2 Imports (current implementation)
use Filament\Schemas\Schema;

// ✅ Unchanged imports
use Filament\Tables\Table;
use Filament\Resources\Resource;
```

## Component Namespace Changes

### Form Field Components (UNCHANGED)

**✅ Form field components remain in Forms namespace:**

```php
// ✅ CORRECT: Form field components stay in Forms namespace
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
```

### Layout Components (MOVED TO SCHEMAS)

**✅ Layout components moved to Schema namespace:**

```php
// ✅ CORRECT: Layout components moved to Schemas namespace in v4
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Wizard;
```

### Table Components (UNCHANGED)

```php
// ✅ Table columns remain in Tables namespace
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
```

### Migration Example

#### Before (v3)
```php
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;

public static function form(Form $form): Form
{
    return $form->schema([
        Section::make('Details')->schema([
            Grid::make(2)->schema([
                TextInput::make('name'),
                TextInput::make('email'),
            ])
        ])
    ]);
}
```

#### After (v4 Beta2)
```php
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

public static function form(Schema $schema): Schema
{
    return $schema->components([
        Section::make('Details')->schema([
            Grid::make(2)->schema([
                TextInput::make('name'),
                TextInput::make('email'),
            ])
        ])
    ]);
}
```

**Key Changes**: Method signature, core import, root method call, and layout component imports.

## Verified Breaking Changes (From Official Docs)

### High-Impact Changes

#### File Visibility is Private by Default
In v4, file visibility is set to `private` by default (was `public` in v3). This affects:
- `FileUpload` form field
- `ImageColumn` table column  
- `ImageEntry` infolist entry

```php
// v3 defaults
'default_disk' => 'public',
'default_visibility' => 'public',

// v4 defaults
'default_disk' => 'local',
'default_visibility' => 'private',
```

**Revert to v3 behavior:**
```php
// In AppServiceProvider boot() method
use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\ImageEntry;
use Filament\Tables\Columns\ImageColumn;

FileUpload::configureUsing(fn (FileUpload $fileUpload) => $fileUpload
    ->visibility('public'));

ImageColumn::configureUsing(fn (ImageColumn $imageColumn) => $imageColumn
    ->visibility('public'));

ImageEntry::configureUsing(fn (ImageEntry $imageEntry) => $imageEntry
    ->visibility('public'));
```

#### Custom Themes Need Tailwind CSS v4
Custom theme CSS files must be updated:

```css
/* v3 */
@import '../../../../vendor/filament/filament/resources/css/theme.css';
@config 'tailwind.config.js';

/* v4 */
@import '../../../../vendor/filament/filament/resources/css/theme.css';
@source '../../../../app/Filament';
@source '../../../../resources/views/filament';
```

#### Table Filters are Deferred by Default
The `deferFilters()` method is now default behavior - users must click a button before filters apply.

**Revert to v3 behavior:**
```php
// In AppServiceProvider boot() method
use Filament\Tables\Table;

Table::configureUsing(fn (Table $table) => $table
    ->deferFilters(false));
```

#### Layout Components No Longer Span Full Width
Grid, Section, and Fieldset components now only consume one column by default.

**Add full width back:**
```php
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

Section::make()->columnSpanFull()
Grid::make()->columnSpanFull()
Fieldset::make()->columnSpanFull()
```

**Revert globally:**
```php
// In AppServiceProvider boot() method
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

Fieldset::configureUsing(fn (Fieldset $fieldset) => $fieldset
    ->columnSpanFull());

Grid::configureUsing(fn (Grid $grid) => $grid
    ->columnSpanFull());

Section::configureUsing(fn (Section $section) => $section
    ->columnSpanFull());
```

### Badge Column Deprecation

**v3 Pattern** (deprecated in v4):
```php
use Filament\Tables\Columns\BadgeColumn;

BadgeColumn::make('status')
    ->colors([
        'success' => 'active',
        'warning' => 'processing',
        'danger' => 'archived',
    ]);
```

**v4 Pattern** (recommended):
```php
use Filament\Tables\Columns\TextColumn;

TextColumn::make('status')
    ->badge()
    ->color(fn (string $state): string => match ($state) {
        'active' => 'success',
        'processing' => 'warning',
        'archived' => 'danger',
        default => 'gray',
    });
```

## Migration Strategy

### 1. Automated Migration (Recommended)

```bash
# Complete automated upgrade process
composer config minimum-stability beta
composer require filament/upgrade:"^4.0" -W --dev
vendor/bin/filament-v4
```

**Note**: The automated script uses Rector v2 for code transformations and handles most breaking changes automatically, though **manual review remains necessary**.

### 2. Manual Migration Steps

#### Update Method Signatures
```php
// Replace Form-based method signatures:
- use Filament\Forms\Form;
- public static function form(Form $form): Form
- return $form->schema([...]);

// With Schema-based signatures (Beta2):
+ use Filament\Schemas\Schema;
+ public static function form(Schema $schema): Schema
+ return $schema->components([...]);
```

#### Update Component Imports
```php
// Move ONLY layout components to Schema namespace:
- use Filament\Forms\Components\Grid;
- use Filament\Forms\Components\Section;
- use Filament\Forms\Components\Fieldset;

+ use Filament\Schemas\Components\Grid;
+ use Filament\Schemas\Components\Section;
+ use Filament\Schemas\Components\Fieldset;

// Keep form field components in Forms namespace:
✅ use Filament\Forms\Components\TextInput;
✅ use Filament\Forms\Components\Select;
✅ use Filament\Forms\Components\Hidden;
```

#### Update Table Column Usage
```php
// Replace BadgeColumn usage:
- BadgeColumn::make('status')->colors([...])

// With TextColumn badge:
+ TextColumn::make('status')->badge()->color(fn ($state) => match($state) {...})
```

## Beta vs Official Documentation Discrepancy

**Important Note**: There's a discrepancy between the official migration documentation and the actual beta2 implementation:

| Aspect | Official Docs Say | Beta2 Implementation |
|--------|------------------|---------------------|
| Method Signatures | `form(Form $form): Form` unchanged | `form(Schema $schema): Schema` required |
| Method Calls | `->schema([])` unchanged | `->components([])` required |
| Core Imports | `use Filament\Forms\Form` | `use Filament\Schemas\Schema` |

This guide documents the **working beta2 implementation**. Future stable releases may align with the official documentation.

## Verification Checklist

After migration, verify:

- [ ] **Layout components** use `Filament\Schemas\Components\` (Grid, Section, Fieldset)
- [ ] **Form field components** use `Filament\Forms\Components\` (TextInput, Select, etc.)
- [ ] **Table columns** still use `Filament\Tables\Columns\`
- [ ] Form method signatures updated to `form(Schema $schema): Schema`
- [ ] Table method signatures remain `table(Table $table): Table`
- [ ] Form method calls use `->components([])` pattern
- [ ] Table method calls still use `->columns([])` pattern
- [ ] Core imports changed from `Form` to `Schema`
- [ ] Badge columns updated to `TextColumn->badge()`
- [ ] No references to deprecated `BadgeColumn`
- [ ] File storage defaults reviewed (now `local`/`private`)
- [ ] Layout components span full width if needed (`->columnSpanFull()`)
- [ ] Filter behavior updated (`deferFilters(false)` if needed)
- [ ] Custom themes upgraded to Tailwind CSS v4

## Performance Improvements

Filament v4 delivers significant performance improvements:
- Tables render approximately **2x faster** (confirmed by Dan Harrin)
- Performance gains from eliminating excessive Blade view rendering
- Direct PHP HTML generation reduces file includes significantly
- Enhanced partial rendering capabilities

## Conclusion

Filament v4 represents a significant architectural evolution focused on performance and developer experience. While the beta implementation differs from some official documentation, the changes enable enhanced capabilities through unified schema architecture.

**Key Takeaway**: Test thoroughly with the beta version you're using, as implementation details may still be evolving before the stable release.

## Related Documentation

- [03_filament_interface.md](03_filament_interface.md) - Filament admin interface usage
- [07_troubleshooting.md](07_troubleshooting.md) - Common issues and solutions
- [Official Filament v4 Upgrade Guide](https://filamentphp.com/docs/4.x/upgrade-guide)

---

**Last Updated**: June 2025  
**Filament Version**: 4.0-beta2  
**Migration Script**: `composer require filament/upgrade:"^4.0" -W --dev && vendor/bin/filament-v4`