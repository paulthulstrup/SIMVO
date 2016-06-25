<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\User;
use App\Schedule;
use DB;
use Auth;

trait ProgramTrait
{
  use ParsingTrait;
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
    $majors_PDO = DB::table('Programs')
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
  * Function that returns All Groups In a certain Major
  * @param int: ProgramID
  * @return String array of all Group names
  **/
  public function getGroups($programID)
  {
    $groups_PDO = DB::table('Programs')
                  ->where('PROGRAM_ID', $programID)
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
  public function getRequiredGroups($programID)
  {
    $user = Auth::User();

    $version = $this->getProgramVersion($user);

    $groups_PDO = DB::table('Programs')
                  ->where('VERSION', $version)
                  ->where('PROGRAM_ID', $programID)
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
  * Function that returns All Groups In a certain Major
  * @param int: ProgramID
  * @return String array of all Group names and credit count
  **/
  public function getGroupsWithCredits($programID)
  {
    $user = Auth::User();

    $version = $this->getProgramVersion($user);

    $groups_PDO = DB::table('Programs')
                  ->where('VERSION', $version)
                  ->where('PROGRAM_ID', $programID)
                  ->whereNotNull('SUBJECT_CODE')
                  ->whereNotNull('COURSE_NUMBER')
                  ->groupBy('SET_TITLE_ENGLISH')
                  ->get(['SET_TITLE_ENGLISH', 'SET_BEGIN_TEXT_ENGLISH']);

    $groups = [];

    foreach($groups_PDO as $group)
    {
      if(!is_null($group->SET_TITLE_ENGLISH) && !is_null($group->SET_BEGIN_TEXT_ENGLISH))
      {
        $groups[$group->SET_TITLE_ENGLISH] = $this->extractCreditFromDesc($group->SET_BEGIN_TEXT_ENGLISH);
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
  public function getGroupsWithCourses($programID, $filter)
  {
    $groups = $this->getRequiredGroups($programID);

    foreach($groups as $key=>$value)
    {
      $groups[$key] = $this->getCoursesInGroup($programID, $key, $filter);
    }

    return $groups;
  }

  /**
  * Function that returns all courses in a group
  * @param int: ProgramID, group, filter(if you want to exclude courses already in schedule)
  * @return array of arrays containing course information
  **/
  public function getCoursesInGroup($programID, $group, $filter)
  {
    $user = Auth::User();

    $version = $this->getProgramVersion($user);

    $courses_PDO = DB::table('Programs')
                  ->where('VERSION', $version)
                  ->where('PROGRAM_ID', $programID)
                  ->where('SET_TITLE_ENGLISH', $group)
                  ->get(['SUBJECT_CODE', 'COURSE_NUMBER', 'COURSE_CREDITS','SET_TYPE']);

    $coursesInGroup = [];

    foreach($courses_PDO as $course)
    {
      if($filter)
      {
        $checkIfInSchedule = Schedule::where('user_id', Auth::User()->id)
                             ->where('SUBJECT_CODE', $course->SUBJECT_CODE)
                             ->where('COURSE_NUMBER', $course->COURSE_NUMBER)
                             ->get();
        if(count($checkIfInSchedule) > 0)
          continue;
      }
      if($group == 'Required Year 0 (Freshman) Courses')
      {
        $coursesInGroup[] = [$course->SUBJECT_CODE, $course->COURSE_NUMBER, $course->COURSE_CREDITS, 'Required'];
      }
      else
      {
        $coursesInGroup[] = [$course->SUBJECT_CODE, $course->COURSE_NUMBER, $course->COURSE_CREDITS, $course->SET_TYPE];
      }
    }

    return $coursesInGroup;
  }

  /**
  * Function that returns most recent verion number of program.
  * (Some mojors have multiple programs with the same program ID in the database)
  * @param User: user
  * @return int: version number
  **/
  public function getProgramVersion($user)
  {
    $version = DB::table('programs')->where('PROGRAM_ID', $user->programID)
               ->groupBy('VERSION')
               ->orderBy('Version', 'desc')
               ->First(['VERSION']);
    return $version->VERSION;
  }
}