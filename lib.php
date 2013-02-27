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
 * @since 2.0
 * @package    repository_eprintssd
 * @copyright  2013 Abdul Bashet/ULCC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/repository/lib.php');
//require_once($CFG->dirroot . '/repository/eprintssd/epclient.php');


class repository_eprintssd extends repository {
    /** @var int maximum number of thumbs per page */
    const TITLES_PER_PAGE = 10;

    /**
     * eprintssd plugin constructor
     * @param int $repositoryid
     * @param object $context
     * @param array $options
     */
  public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
      //$options['name']= $_POST['title'];
      parent::__construct($repositoryid, $context, $options);
      $this->_options['pluginname'] = 'eprintssd';
    }

    public function check_login() {
        return !empty($this->keyword);
    }

    /**
     * Return search results
     * @param string $search_text
     * @return array
     */

    public function search($search_text, $page = 0) {

        $keyword = $_POST['title'];
        $author = $_POST['creators_name'];
        $year = $_POST['date'];

        $ret  = array();
        $ret['nologin'] = true;
        $ret['page'] = (int)$page;
        if ($ret['page'] < 1) {
            $ret['page'] = 1;
        }

        $ret['list'] = $this->_get_collection($keyword, $author, $year);
        $ret['norefresh'] = true;
        $ret['nosearch'] = true;
        $ret['pages'] = -1;
        return $ret;
    }

    /**
     * Private method to get eprintssd search results
     * @param string $keyword
     * @param int $start
     * @param int $max max results
     * @param string $sort
     * @return array
     */
    private function _get_collection($keyword, $author, $year) {


        if($keyword){
            $content = "http://alto.arts.ac.uk/cgi/search/simple?_action_export=1&output=XML&q=$keyword&_action_search=Search&basic_srchtype=ALL&_satisfyall=ALL";
        }
        if($author){
            $family = $author;
            $given  = $author;
            $content = "http://alto.arts.ac.uk/cgi/exportview/creators/$family=3A$given=3A=3A/XML/$family=3A$given=3A=3A.xml";
        }
        if($year){
            $content = "http://alto.arts.ac.uk/cgi/exportview/year/$year/XML/$year.xml";
        }


        $my_var = file_get_contents($content);

        $xml = simplexml_load_string($my_var);
        //$resultIds = array();

        $TotalFound = count($xml);
        for($index=0;$index < $TotalFound; $index++){
            $result = $xml->eprint[$index];
            $serialNo = $index+1;
            $out []= array(
                'title'=>(string)$result->title,
                //'thumbnail'=>(string)$result->abstruct,
                //'size' => 1,
                //'thumbnail'=> $CFG->wwwroot . '/repository/eprintssd/pix/repo.png',
                //'thumbnail'=> 'http://vl-software.com/moodle/repository/eprintssd/pix/repo.png',
                //'thumbnail_width'=>80,
                //'thumbnail_height'=>36,
                'author' => (string)$result->creators->item->name->given.' '.(string)$result->creators->item->name->family,
                'date' => (string)$result->lastmod,
                //'abstract' => (string)$result->abstruct,
                'source'=>'http://alto.arts.ac.uk/'.(string)$result->eprintid,
                'url' => 'http://alto.arts.ac.uk/'.(string)$result->eprintid);
        }

        return $out;

    }

    /**
     * eprintssd plugin doesn't support global search
     */
    public function global_search() {
        return false;
    }

    public function get_listing($path='', $page = '') {
        return array();
    }

    /**
     * Generate search form
     */
    public function print_login($ajax = true) {
        $ret = array();
        $search1 = new stdClass();
        $search1->type = 'hidden';
        $search1->label = get_string('search', 'repository_eprintssd');

        $search2 = new stdClass();
        $search2->type = 'text';
        $search2->id   = 'title';
        $search2->name = 'title';
        $search2->label = get_string('bytitle', 'repository_eprintssd').': ';

        $search3 = new stdClass();
        $search3->type = 'text';
        $search3->id   = 'creators_name';
        $search3->name = 'creators_name';
        $search3->label = get_string('byauthorsname', 'repository_eprintssd').': ';

        $search4 = new stdClass();
        $search4->type = 'text';
        $search4->id   = 'date';
        $search4->name = 'date';
        $search4->label = get_string('byyearmostrecentfirst', 'repository_eprintssd').': ';

        $ret['login'] = array($search1, $search2, $search3, $search4);
        $ret['login_btn_label'] = get_string('search');
        $ret['login_btn_action'] = 'search';
        $ret['allowcaching'] = true; // indicates that login form can be cached in filepicker.js
        return $ret;
    }

    /**
     * file types supported by eprintssd plugin
     * @return array
     */
    public function supported_filetypes() {
        return array('*');
    }

    /**
     * eprintssd plugin only return external links
     * @return int
     */
    public function supported_returntypes() {
        //return FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE;
        return FILE_EXTERNAL | FILE_REFERENCE;
    }




    public function get_file_reference($source) {
        // Box.net returns a url.
        return $source;
    }




    /**
     * Return human readable reference information
     * {@link stored_file::get_reference()}
     *
     * @param string $reference
     * @param int $filestatus status of the file, 0 - ok, 666 - source missing
     * @return string
     */

    public function get_reference_details($reference, $filestatus = 0) {
        // Indicate it's from box.net repository + secure URL
        /*$array = explode('/', $reference);
        $fileid = array_pop($array);
        $fileinfo = $this->boxclient->get_file_info($fileid, self::SYNCFILE_TIMEOUT);
        if (!empty($fileinfo)) {
            $reference = (string)$fileinfo->file_name;
        }
        $details = $this->get_name() . ': ' . $reference;
        if (!empty($fileinfo)) {
            return $details;
        } else {
            return get_string('lostsource', 'repository', $details);
        }*/
        $details = 'This file is from Alto';
        return $details;
    }

    /**
     * Return the source information
     *
     * @param stdClass $url
     * @return string|null
     */

    public function get_file_source_info($url) {
        if(empty($url)){
            echo 'url not found';
        }
        return $url;
    }

    public function send_file($storedfile, $lifetime=86400 , $filter=0, $forcedownload=false, array $options = null) {
        $ref = $storedfile->get_reference();

        header('Location: ' . $ref);
    }

}
