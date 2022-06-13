<?php
/**
 * SmartCounter 0.3 alpha
 * 
 * Alexander Dalle dalle@criptext.com 
 * 
 * Simple JSON based webcounter
 * 
 */

namespace FriendlyWeb;

use DateTime;

class SmartCounter
{

    private $statisticsFilePath = 'user/smartcounter.json';
    private $statistics = null;
    private $uri = null;
    private $pagekey = null;

    public function __construct()
    {

        $this->setCurrentDate();

        $this->getStatsData();

        $this->setURI();

        $this->setPageKey();

        $this->updateStatsData();

    }

    private function setCurrentDate()
    {

        $this->datenow = new DateTime('NOW');

    }

    private function getStatsData()
    {

        if (file_exists($this->statisticsFilePath)) {

            $this->statistics = json_decode(file_get_contents($this->statisticsFilePath), false);

        } else {

            $this->statistics = (object)$this->createSkeleton();

        }

    }

    /**
     * Understanding Skeleton
     * 
     * date - date of last editing data file
     * pages - array of objects (statistics by URI pages)
     *  uri  -  resource identifier
     *  hits -  array as a sequence of days
     *  hosts - array as a sequence of days
     */
    private function createSkeleton()
    {

        $pages[] = $this->smallSkeleton();

        $skeleton = array(
            'date'      => $this->datenow->format('Y-m-d'),
            'pages'    => $pages);

        return $skeleton;

    }

    private function smallSkeleton($uri = 'index')
    {
        $sq = array(0);
        $empty = array(
            'uri' => $uri,
            'hits'=>$sq,
            'hosts'=>$sq
        );

        return (object)$empty;

    }

    // Set current key of pages array in $this->statistics by current URI
    private function setPageKey()
    {

        foreach ($this->statistics->pages as $key => $page) {

            if ($page->uri == $this->uri) {

                $this->pagekey = $key;

            }

        }

    }

    /**
     * Fill 0 values of the days before the current date
     * 
     * $sq - array
     * 
     * return Array 
     */
    private function subsequence($sq)
    {

        $startdate = new DateTime($this->statistics->date);

        $days = $this->daysBefore($startdate);

        for ($i=0; $i<$days; $i++) {

            $sq[] = 0;

        }

        return $sq;

    }

    /**
     * Days before the CURRENT date
     * $lastdate - DateTime object
     */ 
    private function daysBefore($startdate)
    {

        $days = $this->datenow->diff($startdate)->format("%a");

        return $days;

    }

    private function addTolastOne($sq) 
    {

        end($sq);

        $key = key($sq);

        $sq[$key]++;

        return $sq;

    }

    private function addPage()
    {

        $this->statistics->pages[] = $this->smallSkeleton($this->uri);

    }

    private function updateStatsData()
    {

        if ($this->pagekey === null) {

            $this->addPage();

            $this->setPageKey();

        }

        foreach ($this->statistics->pages as $key => $page) {

            $hh = array("hits", "hosts");

            foreach ($hh as $h) {

                $this->statistics->pages[$key]->$h = $this->subsequence($page->$h);

            }

        }

        $this->statistics->date = $this->datenow->format('Y-m-d');

    }

    public function count()
    {

        $this->statistics->pages[$this->pagekey]->hits = $this->addTolastOne($this->statistics->pages[$this->pagekey]->hits);
        
        if ($this->isNewVisitor()) {

            $this->statistics->pages[$this->pagekey]->hosts = $this->addTolastOne($this->statistics->pages[$this->pagekey]->hosts);

        }

        $this->setCookie();

        $this->putJSONtoFile();

    }

    private function isNewVisitor()
    {

        $this->getCookie();

        if (isset($this->cookiedate)) {

            if ( $this->daysBefore($this->cookiedate) != 0) {

                return true;

            } else {

                return false;
            }

        } else {

            return true;

        }

    }

    private function setCookie()
    {

        $domainname = $_SERVER['SERVER_NAME']; // bug with 'localhost'

        setcookie ('smartcounter', $this->statistics->date, time()+87091200, '/', ".$domainname");

    }

    private function getCookie()
    {

        if (isset($_COOKIE["smartcounter"])) {

            $datecookie = $_COOKIE["smartcounter"];

            if ($this->verifyDate($datecookie)) {

                $this->cookiedate = new DateTime($datecookie);

            } else {
                // wrong cookie date format 
            }

        }

    }

    private function verifyDate($date)
    {

        return (DateTime::createFromFormat('Y-m-d', $date) !== false);

    }

    private function putJSONtoFile()
    {

        file_put_contents($this->statisticsFilePath, json_encode($this->statistics));

    }

    public function rawStats()
    {

        return json_encode($this->statistics);

    }

    private function getURI()
    {

        $uri = urldecode($_SERVER['REQUEST_URI']);

        $uri = substr($uri, 1); // Remove slash at the start of the line

        if ($uri == '') {

            return 'index';

        } else {

            return $uri;

        }

    }

    private function setURI()
    {

        $this->uri = $this->getURI();

    }

}

?>