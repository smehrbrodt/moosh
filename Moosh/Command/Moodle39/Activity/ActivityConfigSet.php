<?php
/**
 * moosh - Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh\Command\Moodle39\Activity;
use Moosh\MooshCommand;

class ActivityConfigSet extends MooshCommand
{
    public function __construct()
    {
        parent::__construct('config-set', 'activity');

        $this->addOption('s|sectionnumber:=number', 'sectionnumber', null);
        $this->addOption('u|update-events', 'Update matching dashboard events');

        $this->addArgument('mode');
        $this->addArgument('id');
        $this->addArgument('module');
        $this->addArgument('setting');
        $this->addArgument('value');

        $this->minArguments = 5;
    }

    public function execute()
    {
        $mode = $this->arguments[0];
        $id = $this->arguments[1];
        $modulename = $this->arguments[2];
        $setting = trim($this->arguments[3]);
        $value = trim($this->arguments[4]);

        $options = $this->expandedOptions;
        $sectionnumber = $options['sectionnumber'];

        switch ($this->arguments[0]) {
            case 'activity':
                if(!self::setActivitySetting($modulename, $id/* activityid */,$setting,$value)){
                    // the setting was not applied, exit with a non-zero exit code
                    cli_error('');
                }
                break;
            case 'course':
                //get all activities in the course
                $our_mod_info = get_fast_modinfo($id/* courseid */)->get_instances_of($modulename);
                $updatelist = array();
                foreach ($our_mod_info as $instance => $mod) {
                    if ( empty( $sectionnumber ) ) {
                        $updatelist[] = $mod;
                    }
                    elseif ( !empty($sectionnumber) and $mod->sectionnum == $sectionnumber ) {
                        $updatelist[] = $mod;
                    }
                }
                $succeeded = 0;
                $failed = 0;
                foreach ($updatelist as $activity) {
                    if(self::setActivitySetting($modulename,$activity->instance,$setting,$value)){
                        $succeeded++;
                    }else{
                        $failed++;
                    }
                }
                if($failed == 0){
                    echo "OK - successfully modified $succeeded activities\n";
                }else{
                    echo "WARNING - failed to modify $failed activities (successfully modified $succeeded)\n";
                }
                break;
        }

    }

    private function setActivitySetting($modulename, $activityid, $setting, $value) {

        global $DB;
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/calendar/lib.php');

        if (!$DB->set_field($modulename, $setting, $value, array('id' => $activityid))) {
            echo "ERROR - failed to set $setting='$value' ($modulename activityid={$activityid})\n";
            return false;
        }
        echo "OK - Set $setting='$value' ($modulename activityid={$activityid})\n";

        if (!$this->expandedOptions['update-events']) {
            return true;
        }

        // 'course_modules' is not an activity table: There the id we were given is a course
        // module id and not an activity instance id, and the only date it holds
        // ('completionexpected') has an event of its own.
        if ($modulename == 'course_modules') {
            $cm = get_coursemodule_from_id('', $activityid);
            if (!$cm) {
                echo "WARNING - no course module with id {$activityid}, calendar events not updated\n";
                return true;
            }
            if ($setting == 'completionexpected') {
                $this->updateCompletionEvent($cm);
            }
            return true;
        }

        $cm = get_coursemodule_from_instance($modulename, $activityid);
        if (!$cm) {
            echo "WARNING - no course module for $modulename instance {$activityid}, calendar events not updated\n";
            return true;
        }
        $this->refreshModuleEvents($cm);

        return true;
    }

    /**
     * Recreate the calendar events of an activity from its current settings.
     *
     * We let the activity module do that itself, the same way Moodle does when the activity is
     * saved in the UI (see edit_module_post_actions()): The module knows the name and the type of
     * each of its events, and it also creates events which are still missing and deletes the ones
     * whose date we just cleared.
     *
     * @param \stdClass $cm course module, as returned by get_coursemodule_from_*()
     */
    private function refreshModuleEvents($cm) {
        global $CFG;

        require_once($CFG->dirroot . '/mod/' . $cm->modname . '/lib.php');
        $refreshevents = $cm->modname . '_refresh_events';
        if (!function_exists($refreshevents)) {
            echo "WARNING - {$refreshevents}() does not exist, calendar events not updated\n";
            return;
        }
        $refreshevents($cm->course, $cm->instance, $cm);
        echo "OK - Refreshed calendar events of {$cm->modname} instance {$cm->instance}\n";

        // The module only knows about its own events and treats the "expect completed on" event as
        // a leftover of its own, so it deletes it. Moodle has the same problem and solves it by
        // recreating that event afterwards - so that is what we do, too.
        $this->updateCompletionEvent($cm);
    }

    /**
     * Sync the "expect completed on" event of a course module with its completionexpected date.
     *
     * @param \stdClass $cm course module, as returned by get_coursemodule_from_*()
     */
    private function updateCompletionEvent($cm) {
        global $DB, $CFG;

        // core_completion\api uses completion_info, which is not autoloaded.
        require_once($CFG->libdir . '/completionlib.php');

        $completionexpected = $DB->get_field('course_modules', 'completionexpected',
                array('id' => $cm->id));
        // A null date deletes the event, which is what we want when no date is set.
        \core_completion\api::update_completion_date_event($cm->id, $cm->modname, $cm->instance,
                $completionexpected ? (int)$completionexpected : null);
        echo "OK - Set expected completion event of course module {$cm->id} to '{$completionexpected}'\n";
    }

    protected function getArgumentsHelp()
    {
        return "\n\nARGUMENTS:\n\tactivity activityid moduletype setting value\n\tOr...\n\tcourse courseid[all] moduletype setting value";
    }

}
