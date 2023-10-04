<?php
/****************************************************************************
    MailListStat - print useful statistics on email messages
    PHP wrapper class
    Copyright (C) 2001-2003  Marek Podmaka

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 ****************************************************************************/

/*** USAGE:
Just create an instance of this class, set options and call method Run(), it
will produce complete html with header and footer. You should set at least
$input and $is_cache.

===cut===example===list.php===
require("mls.php");
$mls=new MailListStat;
$mls->default_lang="SK";
$mls->input="/home/marki/list.cache";
$mls->is_cache=true;
$mls->Run();
===cut===

***/

class MailListStat {
 // mailing list name (-t)
 var $name="";
 // or title (-T)
 var $title="";
 // name of input file
 var $input="/home/marki/mobil.cache";
 // is input file Cache or MBOX? (using cache file is recommended)
 var $is_cache=true;
 // path to mls executable
 var $path="/usr/local/bin/mls";
 // graphs to show (-g in mls; don't specify to use default in mls)
 var $graph="";
 // default values for LANG & topXX
 var $default_lang="EN";
 var $default_topX="10";
 // alter email addresses in output to prevent spam?
 // Specify regexp search & replace patterns; empty to do nothing
 // Be careful and look at the ereg_replace & preg_replace manual
 // "/$email_srch/" will used for preg_replace
 var $email_srch="@";
 var $email_repl=" (at) ";
 //$email_repl="@NOSPAM.";
 var $lang="";
 var $topX="";
 var $timer_start=0;

 function MailListStat() { // constructor
   $this->timer_start=microtime();
 }// constructor

 function Run() { // main function called by user
   global $HTTP_GET_VARS;
   // get user submitted values (or use default)
   $this->lang=$HTTP_GET_VARS['lang']; // language (-l option in mls)
   $this->topX=$HTTP_GET_VARS['topX']; // print topXX (-n)
   if (!$this->lang) $this->lang=$this->default_lang;
   if (!$this->topX) $this->topX=$this->default_topX;
   // check user (web) submitted data for validity
   if (!eregi("^[a-z]{2}$",$this->lang) || !eregi("^[0-9]{1,3}$",$this->topX))
      $this->error("You have specified invalid parameters!");
   echo $this->RunMLS(); // run mls & print output
   $this->timeEnd();
 }// Run()

 function error($text) {
   echo "<HTML><HEAD><title>MailListStat - error</title></HEAD><BODY>\n";
   echo "<p><b>ERROR:</b> $text</p>\n";
   echo "<p>Generated by: <a href='https://github.com/marki555/MailListStat'>MailListStat</a> PHP wrapper.</p>\n";
   die("</BODY></HTML>");
 }// error()

 function RunMLS() { // run mls & return modified output
   if ($this->is_cache) $inp="-r";
                   else $inp="-i";
   $exec=escapeShellCmd($this->path);
   $exec.=" -m html"; // output mode
   if ($this->name)  $exec.=" -t ".escapeShellArg($this->name);
   if ($this->title) $exec.=" -T ".escapeShellArg($this->title);
   if ($this->graph) $exec.=" -g ".escapeShellArg($this->graph);
   $exec.=" -n $this->topX -l $this->lang $inp ".escapeShellArg($this->input);
   $exec.=" -q"; // quiet
   exec($exec, $a_out, $ret);
   // check return value
   switch($ret) {
    case   1: $this->error("Cache file has wrong format!");
    case   2: $this->error("Invalid parameters while executing mls!");
    case   3: $this->error("Cannot open input file!");
    case   4: $this->error("Not enough memory!");
    case 127: $this->error("Cannot run MLS!");
    case   0: break; // no error
    default : $this->error("Unknown error ($ret)!");
   }
   $buffer=implode("\n", $a_out);
   $buff=ereg_replace("<!-- === -->", $this->PrepareLinks(), $buffer);
   if ($this->email_srch) {
     if (function_exists("preg_replace"))
       return preg_replace("/$this->email_srch/", $this->email_repl, $buff);
     else
       return ereg_replace($this->email_srch    , $this->email_repl, $buff);
   } else return $buff;
 }// RunMLS()

 function PrepareLinks() { // prepare links for changing LANG & topXX
   global $HTTP_SERVER_VARS;
   $self=$HTTP_SERVER_VARS['PHP_SELF'];
   $added="<center>\n";
   $added.="<b>Language:</b>&nbsp;[&nbsp;";
   $a_lang1=array("de","en","fr","it","sk","es","sr","br");
   $a_lang2=array("Deutsch","English","Francais","Italiano","Slovak","Spanish","Serbian","Portugues Brasil");
   for ($i=0; $i < count($a_lang1); $i++) {
     $o1=$a_lang1[$i];
     $o2=$a_lang2[$i];
     if (StrToUpper($this->lang)!=StrToUpper($o1)) $o2="<a href=\"$self?topX=$this->topX&lang=$o1\">$o2</a>";
     $o2.="&nbsp;";
     if ($i) $o2="|&nbsp;".$o2;
     $added.=$o2;
   }
   $added.="]<br>\n<b>Print TOP:</b>&nbsp;[&nbsp;";
   $a_topX=array("5","10","20","25","50","100","200","250","500","999");
   for ($i=0; $i < count($a_topX); $i++) {
     $o1=$a_topX[$i];
     if ($this->topX != $o1) $o1="<a class=\"wr\" href=\"$self?topX=$o1&lang=$this->lang\">$o1</a>";
     $o1.="&nbsp;";
     if ($i) $o1="|&nbsp;".$o1;
     $added.=$o1;
   }
   $added.="]\n</center><p>\n";
   return $added;
 }// PrepareLinks()

 function timeEnd() { // computing of consumed time
   $timer_end=microtime();
   ereg('0(\..*) (.*)',$this->timer_start,$t_s);
   ereg('0(\..*) (.*)',$timer_end,$t_e);
   $timer_s_time=$t_s[2].$t_s[1];
   $timer_e_time=$t_e[2].$t_e[1];
   $timer_elapsed=sprintf("%.4f",$timer_e_time-$timer_s_time);
   echo "\n<!-- Generated by MailListStat PHP wrapper in: $timer_elapsed second(s) -->\n";
 }// timeEnd()

} /** class MailListStat **/

?>
