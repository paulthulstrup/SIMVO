<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\User;
use App\Stream;
use App\StreamStructure;
use App\Schedule;
use App\Custom;
use App\Internship;
use Debugbar;
use DB;
use Auth;
use Session;


trait ProgramTrait
{

  use ParsingTrait;
  /**
  * Function that returns groups and the number of credits in them along
  * with the number of credits the user has completed in each group
  * @param current logged in user
  * @return array with key = groupName => [credits taken,total dredits in group]
  **/
  public function generateProgressBar($degree)
  {
    $groups = $this->getGroupsWithCredits($degree);

    $progress = [];

    foreach ($groups as $key=>$value)
    {
      $courses = $this->getCoursesInGroup($degree, $key, false);

      $totCredits = $value;
      $creditsTaken = 0;
      foreach($courses as $course)
      {
        $check = Schedule::where('degree_id', $degree->id)
                 ->where('SUBJECT_CODE', $course[0])
                 ->where('COURSE_NUMBER', $course[1])
                 ->get();

        if(count($check)>0)
          $creditsTaken += $this->getCourseCredits($course[0], $course[1]);
      }

      $checkCustom = Custom::where('degree_id', $degree->id)
                ->where('focus', $key)
                ->get();

      foreach($checkCustom as $course)
      {
        $creditsTaken += (int) $course->credits;
      }

      $progress[$key] = [$creditsTaken,$value];
    }
    return $progress;
  }


  public function getAllSchedId($degree)
  {
    $idArray = [];

    $schedules = Schedule::where('degree_id', $degree->id)->get(['id']);

    foreach($schedules as $sched)
    {
      $idArray[] = $sched->id;
    }

    return $idArray;
  }

  /**
  * Function that returns All Faculties in University
  * @param void
  * @return String array: All faculties in University
  **/
  public function getFaculties()
  {
    $faculties_PDO = DB::table('programs')
                  ->groupBy('PROGRAM_TEACHING_FACULTY')
                  ->get(['PROGRAM_TEACHING_FACULTY']);
    $faculties = [];

    foreach($faculties_PDO as $fac)
    {
      $faculties[] = $fac->PROGRAM_TEACHING_FACULTY;
    }

    return $faculties;
  }

  /**
  * Function that returns All Majors In a certain Faculty
  * @param String: Faculty name
  * @return String array of all faculties
  **/
  public function getMajors($faculty)
  {
    $majors_PDO = DB::table('programs')
                  ->where('PROGRAM_TEACHING_FACULTY', $faculty)
                  ->where('FIELD_OF_STUDY', 'MAJOR')
                  ->groupBy('PROGRAM_MAJOR')
                  ->get(['PROGRAM_MAJOR', 'PROGRAM_ID']);

    $majors = [];

    foreach($majors_PDO as $major)
    {
      $majors[] = [$major->PROGRAM_MAJOR,$major->PROGRAM_ID];
    }

    return $majors;
  }

  /**
  * Function that returns All Minors  In a certain Faculty
  * @param String: Faculty name
  * @return String array of all minors with name and program_id
  **/
  public function getMinors()
  {
    $minors_PDO = DB::table('programs')
                  ->where('FIELD_OF_STUDY', 'MINOR')
                  ->groupBy('PROGRAM_MAJOR')
                  ->get(['PROGRAM_MAJOR', 'PROGRAM_ID', 'PROGRAM_TEACHING_FACULTY', 'PROGRAM_TOTAL_CREDITS']);

    $minors = [];

    foreach($minors_PDO as $minor)
    {
      $minors[] = [$minor->PROGRAM_MAJOR,$minor->PROGRAM_ID, $minor->PROGRAM_TEACHING_FACULTY, $minor->PROGRAM_TOTAL_CREDITS];
    }

    return $minors;
  }

  /**
  * Function that returns All Groups In a certain Major
  * @param int: ProgramID
  * @return String array of all Group names
  **/
  public function getGroups($degree)
  {
    $groups_PDO = DB::table('programs')
                  ->where('PROGRAM_ID', $degree->program_id)
                  ->whereNotNull('SUBJECT_CODE')
                  ->whereNotNull('COURSE_NUMBER')
                  ->groupBy('SET_TITLE_ENGLISH')
                  ->get(['SET_TITLE_ENGLISH', 'SET_BEGIN_TEXT_ENGLISH']);

    $groups = [];

    foreach($groups_PDO as $group)
    {
      if(trim($group->SET_TITLE_ENGLISH) != "")
      {
        $groups[$group->SET_TITLE_ENGLISH] = [];
      }
    }
    return $groups;
  }

  /**
  * Function that returns All Groups In a certain Major
  * @param int: ProgramID
  * @return String array of all Group names
  **/
  public function getRequiredGroups($degree)
  {
    $user = Auth::User();

    $groups_PDO = DB::table('programs')
                  ->where('VERSION', $degree->version_id)
                  ->where('PROGRAM_ID', $degree->program_id)
                  ->where('SET_TYPE', 'Required')
                  ->whereNotNull('SUBJECT_CODE')
                  ->whereNotNull('COURSE_NUMBER')
                  ->groupBy('SET_TITLE_ENGLISH')
                  ->get(['SET_TITLE_ENGLISH', 'SET_BEGIN_TEXT_ENGLISH']);

    $groups = [];

    if(Auth::User()['cegepEntry'] == 0 && Auth::Check())
    {
      $groups['Required Year 0 (Freshman) Courses'] = [];
    }

    foreach($groups_PDO as $group)
    {
      if(trim($group->SET_TITLE_ENGLISH) != "")
      {
        $groups[$group->SET_TITLE_ENGLISH] = [];
      }
    }

    return $groups;
  }

  /**
  * Function that returns All reuqired courses in a major
  * @param int: ProgramID
  * @return String array of all Group names
  **/
  public function getRequiredCourses($degree)
  {
    $requiredGroups = $this->getRequiredGroups($degree);

    $requiredCourses = [];

    foreach($requiredGroups as $group=>$list)
    {
      $coursesinGroup = $this->getCoursesInGroup($degree, $group, false);
      $requiredCourses =  array_merge($requiredCourses, $coursesinGroup);
    }

    return $requiredCourses;
  }

  /**
  * Function that returns All Complementary Groups In a certain Major
  * @param int: ProgramID
  * @return String array of all Group names
  **/
  public function getComplementaryGroups($degree)
  {
    $user = Auth::User();

    $groups_PDO = DB::table('programs')
                  ->where('VERSION', $degree->version_id)
                  ->where('PROGRAM_ID', $degree->program_id)
                  ->where('SET_TYPE', 'Complementary')
                  ->whereNotIn('SET_TITLE_ENGLISH',['Required Year 0 (Freshman) Courses'])
                  ->whereNotNull('SUBJECT_CODE')
                  ->whereNotNull('COURSE_NUMBER')
                  ->groupBy('SET_TITLE_ENGLISH')
                  ->get(['SET_TITLE_ENGLISH', 'SET_BEGIN_TEXT_ENGLISH']);

    $groups = [[],[]];

    foreach($groups_PDO as $group)
    {
      //Index 0 is complementaries ------- Index 1 is electives
      if(trim($group->SET_TITLE_ENGLISH) != "" && strpos($group->SET_TITLE_ENGLISH, "Group", 0 ) === false )
      {
        $groups[0][$group->SET_TITLE_ENGLISH] = [];
      }
      else {
        $groups[1][$group->SET_TITLE_ENGLISH] = [];
      }
    }

    return $groups;
  }


  /**
  * Function that returns All Groups In a certain Major
  * @param int: ProgramID
  * @return String array of all Group names and credit count
  **/
  public function getGroupsWithCredits($degree)
  {
    $user = Auth::User();

    $groups_PDO = DB::table('programs')
                  ->where('VERSION', $degree->version_id)
                  ->where('PROGRAM_ID', $degree->program_id)
                  ->whereNotNull('SUBJECT_CODE')
                  ->whereNotNull('COURSE_NUMBER')
                  ->groupBy('SET_TITLE_ENGLISH')
                  ->get(['SET_TITLE_ENGLISH', 'SET_BEGIN_TEXT_ENGLISH']);

    $groups = [];

    foreach($groups_PDO as $group)
    {
      if(!is_null($group->SET_TITLE_ENGLISH) && !is_null($group->SET_BEGIN_TEXT_ENGLISH))
      {
        $creditsInGroup = $this->extractCreditFromDesc($group->SET_BEGIN_TEXT_ENGLISH);
        if($creditsInGroup > 0)
        {
          $groups[$group->SET_TITLE_ENGLISH] = $creditsInGroup;
        }
      }
    }

    arsort($groups);
    return $groups;
  }

  /**
  * Function that returns All Groups In a certain Major
  * @param int: ProgramID
  * @return String array of all Group names and array of courses in the group
  **/
  public function getGroupsWithCourses($degree, $filter)
  {
    $groups = [];
    $i;
    $courseCount;
    $groups[0] = $this->getRequiredGroups($degree);
    $groups[1] = $this->getComplementaryGroups($degree)[0]; //complementaries
    $groups[2] = $this->getComplementaryGroups($degree)[1]; //electives

    for($i = 0; $i < 3; $i++)
    {
      $courseCount = 0;
      foreach($groups[$i] as $key=>$value)
      {
        $groups[$i][$key] = $this->getCoursesInGroup($degree, $key, $filter);
        $courseCount += count($groups[$i][$key]);
      }
      if($courseCount == 0)
      {
        $groups[$i] = [];
      }
    }

    return $groups;
  }

  /**
  * Function that returns all courses in a group
  * @param int: ProgramID, group, filter(if you want to exclude courses already in schedule)
  * @return array of arrays containing course information
  **/
  public function getCoursesInGroup($degree, $group, $filter)
  {
    $user = Auth::User();

    $group = str_replace("MINOR: ", "", $group);

    $courses_PDO = DB::table('programs')
                  ->where('VERSION', $degree->version_id)
                  ->where('PROGRAM_ID', $degree->program_id)
                  ->where('SET_TITLE_ENGLISH', $group)
                  ->orderBy('COURSE_NUMBER', 'asc')
                  ->get(['SUBJECT_CODE', 'COURSE_NUMBER', 'COURSE_CREDITS','SET_TYPE','COURSE_TITLE']);

    $coursesInGroup = [];

    
    

    if(count($courses_PDO) < 2 && 
    strpos($group, "Complementary") == 0)//check to see if there are courses in the set type
    {
      $courses_PDO = DB::table('programs')
                   ->where('PROGRAM_ID', $degree->program_id)
                   ->where('SET_TYPE', "Complementary") // THIS IS A MEGA HACK, but i don't see another way around this at the moment'
                   ->orderBy('COURSE_NUMBER', 'asc')
                   ->get(['SUBJECT_CODE', 'COURSE_NUMBER', 'COURSE_CREDITS','SET_TYPE','COURSE_TITLE']);
    }

    foreach($courses_PDO as $course)
    {

      if($filter)
      {
        $checkIfInSchedule = Schedule::where('degree_id', $degree->id)
                             ->where('SUBJECT_CODE', $course->SUBJECT_CODE)
                             ->where('COURSE_NUMBER', $course->COURSE_NUMBER)
                             ->get();
        if(count($checkIfInSchedule) > 0)
          continue;
      }
      if($group == 'Required Year 0 (Freshman) Courses')
      {
        $coursesInGroup[] = [$course->SUBJECT_CODE, $course->COURSE_NUMBER, $course->COURSE_CREDITS, 'Required', $course->COURSE_TITLE];
      }
      else
      {
        $coursesInGroup[] = [$course->SUBJECT_CODE, $course->COURSE_NUMBER, $course->COURSE_CREDITS, $course->SET_TYPE, $course->COURSE_TITLE];
      }
    }

    return $coursesInGroup;
  }

  /**
  * Function that returns most recent verion number of program.
  * (Some mojors have multiple programs with the same program ID in the database)
  * @param program_id
  * @return int: version number
  **/
  public function getProgramVersions($program_id)
  {
    $version_PDO = DB::table('programs')->where('PROGRAM_ID', $program_id)
               ->groupBy('VERSION')
               ->orderBy('Version', 'asc')
               ->get(['VERSION']);

    $versions = [];
    foreach($version_PDO as $version)
    {
      $versions[] = $version->VERSION;
    }
    if(count($versions))
      return $versions;
    else
      return [1];
  }

  /**
  * Function that returns all streams of a major and version
  * @param program ID, version
  * @return list of stream names
  **/
  public function getStreams($program_id, $version)
  {
    $streams_PDO = StreamStructure::where('program_id', $program_id)
                   ->where('version', $version)
                   ->groupBy('stream_name')
                   ->get(['id', 'stream_name']);

    $streams = [];
    foreach($streams_PDO as $stream)
    {
      $streams[] = [$stream->id, $stream->stream_name];
    }

    return $streams;
  }

  /**
  * Function that returns lists of semesters by term (either only winter only fall etc)
  * @param string either (all, fall, winter etc)
  * @return list semesters
  **/
  public function getProperSemesters($semesters)
  {
    switch($semesters)
    {
      case "fall":
      return $this->generateListOfFallSemesters(6);
      break;

      default:
      return $this->generateListOfSemesters(10);
      break;
    }
  }

  /**
  * Returns credits in course
  * @param User: Subject code and course number
  * @return int: number of credits in course
  **/
  public function getCourseCredits($sub_code, $course_num)
  {
    return DB::table('programs')->where('SUBJECT_CODE', $sub_code)
               ->where('COURSE_NUMBER', $course_num)
               ->First(['COURSE_CREDITS'])->COURSE_CREDITS;
  }

  /**
   *Returns total remaining credits
   *@param None
   *@Return int: number of credits remaing to take
   **/
   public function getRemainingCredits($degree)
   {
     $progress = [];
     $progress = $this->generateProgressBar($degree);
 
     $creditsTakenSum = 0;
     foreach($progress as $key => $creditsTaken)
     {
       $creditsTakenSum = $creditsTakenSum + $creditsTaken[0];
     }
     return $creditsTakenSum;
   }

   public function getMajorStatus()
   {
     if(!Auth::Check())
      return;
     else
       $user = Auth::User();
 
     $degree = Session::get('degree');
     if($degree == null)
     {
       return;
     }
     $creditsTakenSum = $this->getRemainingCredits($degree);
     return $creditsTakenSum;
  }
}
