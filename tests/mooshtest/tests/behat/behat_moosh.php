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

/**
 * Behat steps definitions for moosh.
 *
 * @package   moosh
 * @category  test
 * @copyright 2019 Tomasz Muras
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ElementNotFoundException;

/**
 * moosh tests.
 *
 * @copyright 2019 Tomasz Muras
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_moosh extends behat_base
{

    /**
     *
     * @Then /^moosh command "(?P<command>.+)" contains "(?P<match>.+)"$/
     */
    public function moosh_command_returns($command, $match)
    {
        $output = null;
        $ret = null;
        exec("php /var/www/html/moosh/moosh.php $command", $output, $ret);

        $matched = false;
        foreach ($output as $line) {
            if (stristr($line, $match) !== false) {
                $matched = true;
                break;
            }
        }
        // For the debugging purposes.
        file_put_contents("/tmp/test.txt", $command . "\n" . $match . "\n" . implode("\n", $output));

        if (!$matched) {
            throw new ElementNotFoundException($this->getSession());
        }
    }
}
