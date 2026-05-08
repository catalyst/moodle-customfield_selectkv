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
 * Key-value select data controller.
 *
 * Stores the selected option's *key* (a string) in the charvalue DB column.
 * The corresponding human-readable label is derived at render time from the field config.
 * If a stored key no longer exists in the mapping (e.g. options were edited), the raw key
 * is returned by export_value() so data is never silently lost.
 *
 * @package   customfield_selectkv
 * @copyright 2026 Catalyst IT Australia
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_controller extends \core_customfield\data_controller {
    /**
     * Return the name of the field where the information is stored.
     *
     * @return string
     */
    public function datafield(): string {
        return 'charvalue';
    }

    /**
     * Returns the default value as it would be stored in the database (the key string).
     *
     * @return string
     */
    public function get_default_value() {
        $defaultvalue = $this->get_field()->get_configdata_property('defaultvalue');
        if ((string) $defaultvalue !== '') {
            $options = $this->get_field()->get_options();
            if (isset($options[$defaultvalue]) && $options[$defaultvalue] !== '') {
                return $defaultvalue;
            }
        }
        return '';
    }

    /**
     * Returns true if this field is configured to allow multiple selections.
     *
     * @return bool
     */
    private function is_multiple(): bool {
        return (bool) $this->get_field()->get_configdata_property('multiplevalues');
    }

    /**
     * Add an autocomplete field element. Multiple selection is enabled or disabled
     * based on the field's 'multiplevalues' configuration.
     *
     * @param \MoodleQuickForm $mform
     */
    public function instance_form_definition(\MoodleQuickForm $mform): void {
        $field = $this->get_field();
        $multiple = $this->is_multiple();

        // Strip the empty placeholder entry — autocomplete does not need it.
        $options = $field->get_options();
        unset($options['']);

        $elementname = $this->get_form_element_name();
        $mform->addElement('autocomplete', $elementname, $field->get_formatted_name(), $options, ['multiple' => $multiple]);

        $defaultkey = $field->get_configdata_property('defaultvalue');
        if ($defaultkey !== '' && isset($options[$defaultkey])) {
            // Multiple mode expects an array; single mode expects a plain string.
            $mform->setDefault($elementname, $multiple ? [$defaultkey] : $defaultkey);
        }

        if ($field->get_configdata_property('required')) {
            $mform->addRule($elementname, null, 'required', null, 'client');
        }
    }

    /**
     * Pre-populates the form element with the stored value.
     *
     * In multiple mode the stored ;-separated string is split into an array.
     * In single mode the stored string key is passed through directly.
     *
     * @param \stdClass $instance
     */
    public function instance_form_before_set_data(\stdClass $instance): void {
        $stored = $this->get_value();
        $elementname = $this->get_form_element_name();
        if ($this->is_multiple()) {
            $instance->{$elementname} = ($stored !== null && $stored !== '') ? explode(';', $stored) : [];
        } else {
            $instance->{$elementname} = (string) ($stored ?? '');
        }
    }

    /**
     * Saves the selected key(s) to charvalue.
     *
     * In multiple mode the submitted array is joined with ';'.
     * In single mode the submitted string is stored directly.
     *
     * @param \stdClass $datanew
     */
    public function instance_form_save(\stdClass $datanew): void {
        $elementname = $this->get_form_element_name();
        if (!property_exists($datanew, $elementname)) {
            return;
        }
        if ($this->is_multiple()) {
            $keys = array_values(array_filter((array) ($datanew->{$elementname} ?? []), fn($k) => $k !== ''));
            $value = implode(';', $keys);
        } else {
            $value = (string) ($datanew->{$elementname} ?? '');
        }
        $this->data->set($this->datafield(), $value);
        $this->data->set('value', $value);
        $this->save();
    }

    /**
     * Validates data for this field.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function instance_form_validation(array $data, array $files): array {
        $errors = parent::instance_form_validation($data, $files);
        if ($this->get_field()->get_configdata_property('required')) {
            // Standard required rule does not work on autocomplete element.
            $elementname = $this->get_form_element_name();
            if (empty($data[$elementname])) {
                $errors[$elementname] = get_string('err_required', 'form');
            }
        }
        return $errors;
    }

    /**
     * Returns the stored keys as a human-readable comma-separated string of labels.
     *
     * Each stored key is mapped to its label. Keys no longer present in the mapping
     * (e.g. options were edited after data was saved) are returned as the raw key
     * so no data is silently lost.
     *
     * @return string|null comma-separated labels, or null if no value is set
     */
    public function export_value() {
        $value = $this->get_value();

        if ($this->is_empty($value)) {
            return null;
        }

        $options = $this->get_field()->get_options();
        $labels = [];

        foreach (explode(';', $value) as $key) {
            if ($key === '') {
                continue;
            }
            // Known key → label; unknown key → raw key string.
            $labels[] = (array_key_exists($key, $options) && $options[$key] !== '') ? $options[$key] : $key;
        }

        return empty($labels) ? null : implode(', ', $labels);
    }
}
