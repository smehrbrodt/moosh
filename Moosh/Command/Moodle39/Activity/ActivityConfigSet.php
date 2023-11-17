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

    private function setActivitySetting($modulename,$activityid,$setting,$value) {

        global $DB;
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/calendar/lib.php');

        if ($DB->set_field($modulename,$setting,$value,array('id'=>$activityid))) {
            echo "OK - Set $setting='$value' ($modulename activityid={$activityid})\n";

	    if (!$this->expandedOptions['update-events'])
		    return true;

	    $select = "modulename = :modulename
                    AND instance = :instance
                    AND groupid = 0
                    AND courseid <> 0";
	    $cm = get_coursemodule_from_instance($modulename, $activityid);
	    $event = new \stdClass();
	    $event->modulename = $modulename;
	    $event->instance = $cm->instance;
	    $event->courseid = $cm->course;
	    $event->timestart = $value;
	    $event->timesort = $value;
        $params = array('modulename' => $modulename, 'instance' => $cm->instance/*, 'eventtype' => 'due'*/);
        $event->id = $DB->get_field_select('event', 'id', $select, $params);
        if ($event->id)
	    {
		    $calendarevent = \calendar_event::load($event->id);
            if ($calendarevent->update($event, false))
                echo "OK - Updating calendar event '{$event->id}' with timestart/timesort '{$value}'\n";
	    }
	    else
	    {
            //TODO: Create event when it doesn't exist - needs some name!
            // $calendarevent = new \calendar_event();
        }
        return true;
        } else {
            echo "ERROR - failed to set $setting='$value' ($modulename activityid={$activityid})\n";
            return false;
        }

    }

    protected function getArgumentsHelp()
    {
        return "\n\nARGUMENTS:\n\tactivity activityid moduletype setting value\n\tOr...\n\tcourse courseid[all] moduletype setting value";
    }

}
