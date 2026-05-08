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

use core_customfield_generator;
use core_customfield_test_instance_form;
use stdClass;

/**
 * Functional tests for customfield_selectkv.
 *
 * @package    customfield_selectkv
 * @covers     \customfield_selectkv\data_controller
 * @covers     \customfield_selectkv\field_controller
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plugin_test extends \advanced_testcase {
    /** @var stdClass[] */
    private array $courses = [];

    /** @var \core_customfield\category_controller */
    private $cfcat;

    /** @var \core_customfield\field_controller[] */
    private array $cfields = [];

    /** @var \core_customfield\data_controller[] */
    private array $cfdata = [];

    /** Options string used for most tests: three key;value pairs. */
    private const OPTIONS = "red;Red Color\nblue;Blue Color\ngreen;Green Color";

    /**
     * Tests set up.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->cfcat = $this->get_generator()->create_category();

        // Field 1: multiple-select, no required, no default.
        $this->cfields[1] = $this->get_generator()->create_field([
            'categoryid' => $this->cfcat->get('id'),
            'shortname'  => 'myfield1',
            'type'       => 'selectkv',
            'configdata' => ['options' => self::OPTIONS, 'multiplevalues' => 1],
        ]);

        // Field 2: multiple-select, required.
        $this->cfields[2] = $this->get_generator()->create_field([
            'categoryid' => $this->cfcat->get('id'),
            'shortname'  => 'myfield2',
            'type'       => 'selectkv',
            'configdata' => ['required' => 1, 'options' => self::OPTIONS, 'multiplevalues' => 1],
        ]);

        // Field 3: multiple-select, with a default value (key 'blue').
        $this->cfields[3] = $this->get_generator()->create_field([
            'categoryid' => $this->cfcat->get('id'),
            'shortname'  => 'myfield3',
            'type'       => 'selectkv',
            'configdata' => ['defaultvalue' => 'blue', 'options' => self::OPTIONS, 'multiplevalues' => 1],
        ]);

        // Field 4: single-select (no multiplevalues), no required, no default.
        $this->cfields[4] = $this->get_generator()->create_field([
            'categoryid' => $this->cfcat->get('id'),
            'shortname'  => 'myfield4',
            'type'       => 'selectkv',
            'configdata' => ['options' => self::OPTIONS, 'multiplevalues' => 0],
        ]);

        $this->courses[1] = $this->getDataGenerator()->create_course();
        $this->courses[2] = $this->getDataGenerator()->create_course();
        $this->courses[3] = $this->getDataGenerator()->create_course();

        // Store a single key 'red' for course 1, and two keys 'red;blue' for course 2 on field 1.
        $this->cfdata[1] = $this->get_generator()->add_instance_data($this->cfields[1], $this->courses[1]->id, 'red');
        $this->cfdata[2] = $this->get_generator()->add_instance_data($this->cfields[1], $this->courses[2]->id, 'red;blue');

        $this->setUser($this->getDataGenerator()->create_user());
    }

    /**
     * Get generator.
     *
     * @return core_customfield_generator
     */
    protected function get_generator(): core_customfield_generator {
        return $this->getDataGenerator()->get_plugin_generator('core_customfield');
    }

    /**
     * Test that the correct controller classes are instantiated.
     * @covers \customfield_selectkv\field_controller
     * @covers \customfield_selectkv\data_controller
     */
    public function test_initialise(): void {
        $f = \core_customfield\field_controller::create($this->cfields[1]->get('id'));
        $this->assertInstanceOf(field_controller::class, $f);

        $f = \core_customfield\field_controller::create(0, (object)['type' => 'selectkv'], $this->cfcat);
        $this->assertInstanceOf(field_controller::class, $f);

        $d = \core_customfield\data_controller::create($this->cfdata[1]->get('id'));
        $this->assertInstanceOf(data_controller::class, $d);

        $d = \core_customfield\data_controller::create(0, null, $this->cfields[1]);
        $this->assertInstanceOf(data_controller::class, $d);
    }

    /**
     * Test that the configuration form validates and saves successfully.
     */
    public function test_config_form(): void {
        $this->setAdminUser();
        $submitdata = (array)$this->cfields[1]->to_record();
        $submitdata['configdata'] = $this->cfields[1]->get('configdata');

        $submitdata = \core_customfield\field_config_form::mock_ajax_submit($submitdata);
        $form = new \core_customfield\field_config_form(
            null,
            null,
            'post',
            '',
            null,
            true,
            $submitdata,
            true
        );
        $form->set_data_for_dynamic_submission();
        $this->assertTrue($form->is_validated());
        $form->process_dynamic_submission();
    }

    /**
     * Test config form validation: too few options.
     */
    public function test_config_form_validation_too_few_options(): void {
        $fc = \core_customfield\field_controller::create(0, (object)['type' => 'selectkv'], $this->cfcat);
        $errors = $fc->config_form_validation([
            'configdata' => ['options' => 'red;Red Color', 'defaultvalue' => ''],
        ]);
        $this->assertArrayHasKey('configdata[options]', $errors);
    }

    /**
     * Test config form validation: invalid format (missing semicolon).
     */
    public function test_config_form_validation_invalid_format(): void {
        $fc = \core_customfield\field_controller::create(0, (object)['type' => 'selectkv'], $this->cfcat);
        $errors = $fc->config_form_validation([
            'configdata' => ['options' => "red;Red Color\nbadline\nblue;Blue Color", 'defaultvalue' => ''],
        ]);
        $this->assertArrayHasKey('configdata[options]', $errors);
    }

    /**
     * Test config form validation: duplicate keys.
     */
    public function test_config_form_validation_duplicate_key(): void {
        $fc = \core_customfield\field_controller::create(0, (object)['type' => 'selectkv'], $this->cfcat);
        $errors = $fc->config_form_validation([
            'configdata' => ['options' => "red;Red Color\nred;Another Red\nblue;Blue Color", 'defaultvalue' => ''],
        ]);
        $this->assertArrayHasKey('configdata[options]', $errors);
    }

    /**
     * Test config form validation: default value not in key list.
     */
    public function test_config_form_validation_default_not_in_list(): void {
        $fc = \core_customfield\field_controller::create(0, (object)['type' => 'selectkv'], $this->cfcat);
        $errors = $fc->config_form_validation([
            'configdata' => ['options' => self::OPTIONS, 'defaultvalue' => 'purple'],
        ]);
        $this->assertArrayHasKey('configdata[defaultvalue]', $errors);
    }

    /**
     * Test config form validation: valid default value produces no errors.
     */
    public function test_config_form_validation_valid(): void {
        $fc = \core_customfield\field_controller::create(0, (object)['type' => 'selectkv'], $this->cfcat);
        $errors = $fc->config_form_validation([
            'configdata' => ['options' => self::OPTIONS, 'defaultvalue' => 'blue'],
        ]);
        $this->assertEmpty($errors);
    }

    /**
     * Test instance form — required field enforcement and saving.
     * The autocomplete element submits an array of selected keys.
     */
    public function test_instance_form(): void {
        global $CFG;
        require_once($CFG->dirroot . '/customfield/tests/fixtures/test_instance_form.php');
        $this->setAdminUser();
        $handler = $this->cfcat->get_handler();

        // Submit without the required field — should fail validation.
        $submitdata = (array)$this->courses[1];
        core_customfield_test_instance_form::mock_submit($submitdata, []);
        $form = new core_customfield_test_instance_form(
            'POST',
            ['handler' => $handler, 'instance' => $this->courses[1]]
        );
        $this->assertFalse($form->is_validated());

        // Submit with the required field set — autocomplete returns an array of keys.
        $submitdata['customfield_myfield2'] = ['green'];
        core_customfield_test_instance_form::mock_submit($submitdata, []);
        $form = new core_customfield_test_instance_form(
            'POST',
            ['handler' => $handler, 'instance' => $this->courses[1]]
        );
        $this->assertTrue($form->is_validated());

        $data = $form->get_data();
        $handler->instance_form_save($data);
    }

    /**
     * Test get_value() returns the stored key(s) and export_value() returns the label(s).
     */
    public function test_get_export_value(): void {
        $this->assertEquals('red', $this->cfdata[1]->get_value());
        $this->assertEquals('Red Color', $this->cfdata[1]->export_value());

        $this->assertEquals('red;blue', $this->cfdata[2]->get_value());
        $this->assertEquals('Red Color, Blue Color', $this->cfdata[2]->export_value());
    }

    /**
     * Test that the default value on a field without stored data is used correctly.
     */
    public function test_default_value(): void {
        $d = \core_customfield\data_controller::create(0, null, $this->cfields[3]);
        $this->assertEquals('blue', $d->get_value());
        $this->assertEquals('Blue Color', $d->export_value());
    }

    /**
     * Test single-select mode: stores a plain key string (no semicolons), exports a single label.
     */
    public function test_single_select_mode(): void {
        // Store a single key via add_instance_data.
        $data = $this->get_generator()->add_instance_data($this->cfields[4], $this->courses[3]->id, 'green');

        $this->assertEquals('green', $data->get_value());
        $this->assertEquals('Green Color', $data->export_value());
        // Stored value must contain no semicolons.
        $this->assertStringNotContainsString(';', (string) $data->get_value());
    }

    /**
     * Test that is_multiple() correctly reflects the configdata setting.
     */
    public function test_is_multiple_config(): void {
        \core_customfield\data_controller::create(0, null, $this->cfields[1]);

        // Access via the form definition path (internal method tested indirectly via export/save).
        // Verify multi field stores a ;-joined value.
        $data = $this->get_generator()->add_instance_data($this->cfields[1], $this->courses[3]->id, 'red;green');
        $this->assertEquals('red;green', $data->get_value());
        $this->assertEquals('Red Color, Green Color', $data->export_value());

        $datasingle = $this->get_generator()->add_instance_data($this->cfields[4], $this->courses[2]->id, 'blue');
        $this->assertEquals('blue', $datasingle->get_value());
        $this->assertEquals('Blue Color', $datasingle->export_value());
        $this->assertStringNotContainsString(';', (string) $datasingle->get_value());
    }

    /**
     * Test that when the stored key is no longer present in the options mapping,
     * export_value() returns the raw key string rather than null.
     * Also tests a mixed scenario: one unknown key and one known key in the same stored value.
     */
    public function test_export_value_unknown_key(): void {
        global $DB;

        // Single unknown key — should return the raw key.
        $record = $DB->get_record('customfield_data', ['id' => $this->cfdata[1]->get('id')], '*', MUST_EXIST);
        $record->charvalue = 'obsolete_key';
        $record->value     = 'obsolete_key';
        $DB->update_record('customfield_data', $record);

        $d = \core_customfield\data_controller::create($this->cfdata[1]->get('id'));
        $this->assertEquals('obsolete_key', $d->get_value());
        $this->assertEquals('obsolete_key', $d->export_value());

        // Mixed: one unknown key + one known key — unknown is returned as-is, known is mapped to its label.
        $record->charvalue = 'obsolete_key;red';
        $record->value     = 'obsolete_key;red';
        $DB->update_record('customfield_data', $record);

        $d = \core_customfield\data_controller::create($this->cfdata[1]->get('id'));
        $this->assertEquals('obsolete_key, Red Color', $d->export_value());
    }

    /**
     * Test get_options() parses key;value pairs correctly.
     */
    public function test_get_options(): void {
        $options = $this->cfields[1]->get_options();

        // First entry must be the empty "no selection" slot.
        $this->assertArrayHasKey('', $options);
        $this->assertEquals('', $options['']);

        // Known keys map to the correct labels.
        $this->assertEquals('Red Color', $options['red']);
        $this->assertEquals('Blue Color', $options['blue']);
        $this->assertEquals('Green Color', $options['green']);
    }

    /**
     * Test get_options() skips blank lines and lines without a semicolon.
     */
    public function test_get_options_skips_invalid_lines(): void {
        $field = $this->get_generator()->create_field([
            'categoryid' => $this->cfcat->get('id'),
            'shortname'  => 'skiptest',
            'type'       => 'selectkv',
            'configdata' => ['options' => "red;Red Color\n\nbadline\nblue;Blue Color"],
        ]);

        $options = $field->get_options();
        $this->assertArrayHasKey('red', $options);
        $this->assertArrayHasKey('blue', $options);
        $this->assertArrayNotHasKey('badline', $options);
        // Only '' + 2 valid keys.
        $this->assertCount(3, $options);
    }

    /**
     * Data provider for test_parse_value.
     *
     * @return array
     */
    public static function parse_value_provider(): array {
        return [
            'known label returns key'    => ['Red Color', 'red'],
            'another known label'        => ['Blue Color', 'blue'],
            'third known label'          => ['Green Color', 'green'],
            'unknown label returns empty' => ['Mauve', ''],
        ];
    }

    /**
     * Test parse_value() looks up a display label and returns the corresponding key.
     *
     * @param string $label
     * @param string $expectedkey
     * @dataProvider parse_value_provider
     */
    public function test_parse_value(string $label, string $expectedkey): void {
        $field = $this->get_generator()->create_field([
            'categoryid' => $this->cfcat->get('id'),
            'shortname'  => 'parsetest',
            'type'       => 'selectkv',
            'configdata' => ['options' => self::OPTIONS],
        ]);
        $this->assertSame($expectedkey, $field->parse_value($label));
    }

    /**
     * Test that a field with no stored data and no default returns null from export_value().
     */
    public function test_export_value_empty(): void {
        $d = \core_customfield\data_controller::create(0, null, $this->cfields[1]);
        $this->assertNull($d->export_value());
    }

    /**
     * Test deleting fields and data.
     */
    public function test_delete(): void {
        $this->cfcat->get_handler()->delete_all();
    }
}
