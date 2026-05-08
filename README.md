# customfield_selectkv

A Moodle custom field plugin that provides a dropdown (select) menu where options are defined as **key;value** pairs.

## How it works

- The **key** is stored in the database (`charvalue` column).
- The **value** (label) is shown to the user in the dropdown.
- This decouples the stored identifier from the display text, so labels can be changed without breaking existing data.

## Configuration

When adding a field, enter one `key;value` pair per line in the **Menu options** field:

```
red;Red Color
blue;Blue Color
green;Green Color
```

Optionally set a **Default value** using a key (e.g. `blue`).

## Version support
This plugin has only been tested on Moodle 4.5.

## Support

If you have issues please log them in
[GitHub](https://github.com/catalyst/moodle-auth_saml2/issues).

Please note our time is limited, so if you need urgent support or want to
sponsor a new feature then please contact
[Catalyst IT Australia](https://www.catalyst-au.net/contact-us).
