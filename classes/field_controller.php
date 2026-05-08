<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace customfield_selectkv;

/**
 * Key-value select field controller.
 *
 * Admin configures options as "key;value" pairs, one per line.
 * The key is stored in the database; the value (label) is displayed to the user.
 *
 * @package   customfield_selectkv
 * @copyright 2026 Catalyst IT Australia
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_controller extends \core_customfield\field_controller {
    /**
     * Customfield type
     */
    const TYPE = 'selectkv';

    /**
     * Add fields for editing a selectkv field.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_definition(\MoodleQuickForm $mform): void {
        $mform->addElement('header', 'header_specificsettings', get_string('specificsettings', 'customfield_selectkv'));
        $mform->setExpanded('header_specificsettings', true);

        $mform->addElement('textarea', 'configdata[options]', get_string('menuoptions', 'customfield_selectkv'));
        $mform->setType('configdata[options]', PARAM_TEXT);

        $mform->addElement('text', 'configdata[defaultvalue]', get_string('defaultvalue', 'customfield_selectkv'), 'size="50"');
        $mform->setType('configdata[defaultvalue]', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'configdata[multiplevalues]', get_string('multiplevalues', 'customfield_selectkv'));
        $mform->setType('configdata[multiplevalues]', PARAM_BOOL);
    }

    /**
     * Parse the raw options config string into an associative array of key => label.
     *
     * Lines that are empty or lack a ';' separator are silently skipped.
     * The first entry is always '' => '' (the "no selection" option).
     *
     * @return array associative array ['' => '', 'key1' => 'Label 1', ...]
     */
    public function get_options(): array {
        $optionconfig = $this->get_configdata_property('options');
        $options = ['' => ''];

        if (!$optionconfig) {
            return $options;
        }

        $context = $this->get_handler()->get_configuration_context();
        $lines = preg_split("/\s*\n\s*/", trim($optionconfig), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($lines as $line) {
            $parts = explode(';', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key   = trim($parts[0]);
            $label = trim($parts[1]);
            if ($key === '' || $label === '') {
                continue;
            }
            $options[$key] = format_string($label, true, ['context' => $context]);
        }

        return $options;
    }

    /**
     * Validate the data from the config form.
     *
     * @param array $data from the add/edit field form
     * @param array $files
     * @return array associative array of error messages
     */
    public function config_form_validation(array $data, $files = []): array {
        $errors = [];
        $rawlines = preg_split("/\s*\n\s*/", trim($data['configdata']['options'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);

        $validpairs = [];
        $seenkeys   = [];

        foreach ($rawlines as $line) {
            $parts = explode(';', $line, 2);
            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                $errors['configdata[options]'] = get_string('errorinvalidformat', 'customfield_selectkv');
                return $errors;
            }
            $key = trim($parts[0]);
            if (isset($seenkeys[$key])) {
                $errors['configdata[options]'] = get_string('errorduplicatekey', 'customfield_selectkv', $key);
                return $errors;
            }
            $seenkeys[$key] = true;
            $validpairs[$key] = trim($parts[1]);
        }

        if (count($validpairs) < 2) {
            $errors['configdata[options]'] = get_string('errornotenoughoptions', 'customfield_selectkv');
            return $errors;
        }

        $defaultvalue = $data['configdata']['defaultvalue'] ?? '';
        if ($defaultvalue !== '' && !isset($validpairs[$defaultvalue])) {
            $errors['configdata[defaultvalue]'] = get_string('errordefaultvaluenotinlist', 'customfield_selectkv');
        }

        return $errors;
    }

    /**
     * Does this custom field type support being used as part of the block_myoverview
     * custom field grouping?
     *
     * @return bool
     */
    public function supports_course_grouping(): bool {
        return true;
    }

    /**
     * Return the formatted values for course grouping.
     * Values here are the stored string keys.
     *
     * @param array $values stored key values
     * @return array
     */
    public function course_grouping_format_values($values): array {
        $options = $this->get_options();
        $ret = [];
        foreach ($values as $value) {
            if (isset($options[$value]) && $options[$value] !== '') {
                $ret[$value] = $options[$value];
            }
        }
        $ret[BLOCK_MYOVERVIEW_CUSTOMFIELD_EMPTY] = get_string(
            'nocustomvalue',
            'block_myoverview',
            $this->get_formatted_name()
        );
        return $ret;
    }

    /**
     * Given a display label, return its corresponding key.
     * Returns empty string if not found (used by upload tools).
     *
     * @param string $value display label to look up
     * @return string the corresponding key, or '' if not found
     */
    public function parse_value(string $value): string {
        $options = $this->get_options();
        $key = array_search($value, $options);
        if ($key !== false && $key !== '') {
            return $key;
        }
        return '';
    }
}
