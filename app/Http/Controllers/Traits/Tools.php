<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\User;
use DB;
use Debugbar;

trait Tools
{

  private function convert_term_to_code($term)
  {
    $term = strtolower($term);

    switch($term)
    {
      case 'winter':
      return '01';
      break;

      case 'summer':
      return '05';
      break;

      case 'fall':
      return '09';
      break;
    }
  }
  /**
  * Function that returns Semester that user is currently in
  * @param N/A
  * @return semester in YYYYMM form
  **/
  private function get_current_semester()
  {
    $month=date('m');
    $result=date('Y');

    if($month>=9 && $month<=12)
    {
      $result.="09";
    }

    else if($month>=1 && $month<=4)
    {
      $result.="01";
    }

    else if($month>=5 && $month<=8)
    {
      $result.="05";
    }

    return $result;
  }

  /**
  * Decode Semester
  * @param semester: encoded semester
  **/
  private function get_semester($semester)
  {
    $result="";

    $term=substr($semester, 4, 2);

    switch($term)
    {
      case '01':
      $result.='WINTER ';
      break;

      case '09':
      $result.='FALL ';
      break;

      case '05':
      $result.='SUMMER ';
      break;
    }

    $result.=substr($semester, 0, 4);

    return $result;
  }


  private function get_previous_semester($current)
  {
    $semester="";

    $term=substr($current, 4, 2);
    $curr_year = intval(substr($current, 0, 4));

    switch($term)
    {
      case '01':
      $semester='09';
      $year = $curr_year-1;
      break;

      case '09':
      $semester.='01';
      $year = $curr_year;
      break;

      default:
      $semester.='01';
      $year = $curr_year;
      break;

    }

    $semester=strval($year) . $semester;

    return $semester;
  }

  private function get_next_semester($current)
  {
    $semester="";

    $term=substr($current, 4, 2);
    $curr_year = intval(substr($current, 0, 4));

    switch($term)
    {
      case '01':
      $semester='05';
      $year = $curr_year;
      break;

      case '09':
      $semester.='01';
      $year = $curr_year + 1;
      break;

      case '05':
      $semester.='09';
      $year = $curr_year;
      break;

    }

    $semester=strval($year) . $semester;

    return $semester;
  }

  private function encode_semester($semester)
  {
    $result="";
    $term=explode(" ",$semester);
    $result.=$term[1];

    switch($term[0])
    {
      case 'WINTER':
      $result.='01';
      break;

      case 'FALL':
      $result.='09';
      break;

      case 'SUMMER':
      $result.='05';
      break;
    }

    return $result;
  }

  private function generateListOfSemesters($num)
  {
    $current_semester = $this->get_current_semester();

    $semesters = [$this->get_semester($current_semester)];

    for ($i=0; $i <$num ; $i++)
    {
      array_push($semesters, $this->get_semester($this->get_previous_semester($this->encode_semester($semesters[count($semesters)-1]))));
    }

    return $semesters;
  }

  private function generateListOfFallSemesters($num)
  {
    $current_semester = $this->get_current_semester();

    $fallSemesters = [];

    $currYear = date('Y');

    for($i = 0; $i < $num; $i++)
    {
      $fallSemesters[] = "Fall ". ($currYear-$i);
    }

    return $fallSemesters;
  }
}
