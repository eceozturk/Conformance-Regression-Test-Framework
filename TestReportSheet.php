<?php

require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// Open the test report spreadsheet and write mpdURL and all error logs.
    
function string_operations($locate, $mpdURL)
{
    global $line_count, $MPD_wrote;
    $MPD_wrote = false;
    //get all the files wich end as described below
    $RepLogFiles=glob($locate."/Period0/*logfull.txt");
    $CrossValidDVB=glob($locate."/Period0/*compInfofull.txt");
    $CrossRepDASH=glob($locate."/Period0/*CrossInfofilefull.txt");
    $all_report_files = array_merge($RepLogFiles, $CrossValidDVB, $CrossRepDASH); // put all the filepaths in a single array
    $segment_errors = array();
   
    $xlsx_file = 'TestReport.xlsx'; // the location and name of the report file
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsx_file); //since we create it before hand we read it first and then we write on it
    $sheet = $spreadsheet->getActiveSheet();
    $highCell= $sheet->getHighestDataRow();
    $line_count = 2;//Leave one empty line after each mpd log.
    //
    //
    //
    //Start logging with mpd errors, open mpdreport.txt as a file
  
    $mpdReport_temp = file($locate.'/mpdreport.txt', FILE_IGNORE_NEW_LINES); //since with the flag FILE_IGNORE_NEW_LINES we strip all \n from strings the whole string will be in one line
   
    $mpdReport = array();
    $mpdReport_errors = array();// this will contain only the errors, wornings or informations
    $HbbTV_DVB_info = array(); // this will contain the report on the HbbTV-DVB part
    if($mpdReport_temp !== false)
    {
       
        for($i = 0; $i < count($mpdReport_temp); $i++)//lets strip unuseful lines from the array
        {
            if(($mpdReport_temp[$i] != "") && ($mpdReport_temp[$i][0] != "="))
            {
                $mpdReport[] = $mpdReport_temp[$i];
            }
        }
                          
        for($i = 0; $i < count($mpdReport); $i+=2) //iterate over the lines to do the checking
        {
    
              $j = $i + 1;
            
            // processing the line
            if($mpdReport[$i] == "Start XLink resolving")
            {
         
               
                if($mpdReport[$j] != "XLink resolving successful")
                {
                   
                    while($j<count($mpdReport)&&($mpdReport[$j] != "Start MPD validation") && ($mpdReport[$j] != "HbbTV-DVB Validation "))
                    {
                       $mpdReport_errors[] = $mpdReport[$j];
                       $j++;
                    }
                
                }
            
            }
            
            elseif($mpdReport[$i] == "Start MPD validation")
            {
                if($mpdReport[$j] != "MPD validation successful - DASH is valid!")
                {
                    while($j<count($mpdReport)&&($mpdReport[$j] != "Start Schematron validation") && ($mpdReport[$j] != "HbbTV-DVB Validation "))
                    {
                        $mpdReport_errors[] = $mpdReport[$j];
                        $j++;
                    }
                }
            }
            elseif($mpdReport[$i] == "Start Schematron validation")
            {
                if($mpdReport[$j] != "Schematron validation successful - DASH is valid!")
                {
                    while($j<count($mpdReport)&&$mpdReport[$j] != "HbbTV-DVB Validation ")
                    {
                        $mpdReport_errors[] = $mpdReport[$j];
                        $j++;
                    }
                }
            }
            elseif($mpdReport[$i] == "HbbTV-DVB Validation ")
            {
                while($j < count($mpdReport))
                {
                    $HbbTV_DVB_info[] = $mpdReport[$j];
                    $j++;
                }
                $HbbTV_DVB_info = remove_duplicate_err($HbbTV_DVB_info);
                break; // no need to check the rest
            } 
        }
           
        $mpdReport_errors = remove_duplicate_err($mpdReport_errors);
        $mpdReport_errors = array_merge( $mpdReport_errors, $HbbTV_DVB_info);
        WriteLineToSheet($mpdReport_errors, $sheet, $highCell, 'MPD Report', $mpdURL);

    }

    else
    {
        echo "Error opening mpdreport.txt";
     
    }


$currently=file($all_report_files[0],FILE_IGNORE_NEW_LINES);
    foreach ($all_report_files as $file_location)
    {
 
        $segment_report = file($file_location, FILE_IGNORE_NEW_LINES);
        $segment_errors = array_merge($segment_errors, $segment_report);
             
    }

    
    if(!empty($segment_errors)){
    WriteLineToSheet($segment_errors, $sheet, $highCell, 'Segment Report', $mpdURL);
    }
    if($line_count>3){
    $sheet->mergeCells('A'.($highCell + 2). ':A'.($highCell+$line_count-1));
    $sheet->getColumnDimension('B')->setWidth(200);
    $sheet->getColumnDimension('A')->setWidth(80);
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($xlsx_file);
    }else{
    $sheet->getColumnDimension('B')->setWidth(200);
    $sheet->getColumnDimension('A')->setWidth(80);
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($xlsx_file);
    }
}
function remove_duplicate_err($error_array)
{
    $new_array = array();
    //since we don't have any \n chars in the str we have the whole error string in one line
    for($i = 0; $i < count($error_array); $i++)
    {
        $new_array[$i] = str_word_count($error_array[$i],1);
        $new_array[$i] = implode(" ",$new_array[$i]);
    }
    //add feature to tell how many times an error was repeated
    $count_instances = array_count_values($new_array);
    $new_array = array_unique($new_array);
    foreach ($new_array as $key => $value)//removing some lines that are not necessary
    {
        $repetitions = $count_instances[$value]; 
        if((strlen($value) <= 3) || ($value == "Checks completed") || ($value == 'error'))
        {
            unset($new_array[$key]);
        }
        else 
        {
            $new_array[$key] = "(".$repetitions.' repetition\s) '.$error_array[$key];
        }
    } 
    $last_el = end($new_array);
    if(strpos($last_el, 'Cross representation checks for adaptation set with id') !== false)
    {
        unset($new_array[array_search($last_el,$new_array)]);
    }
    return $new_array;
}

function WriteLineToSheet($contents,$sheet,$highCell, $type, $mpdURL)
{
    global $line_count, $MPD_wrote,$error_counter;
    $next = false;
    if(count($contents)!=0){
    foreach($contents as $line)
    {
        $sheet->setCellValue('B'.($highCell + $line_count), $line);
        $stripped_line = str_word_count($line,1);

        if(!$MPD_wrote)
        {
            $sheet->setCellValue('A'.($highCell + $line_count), $mpdURL);
            $sheet->getCell('A'.($highCell + $line_count))->getHyperlink()->setUrl($mpdURL);
            $sheet->getStyle('A'.($highCell + $line_count))->getFont()->getColor()->setARGB('FF000000');
            $sheet->getStyle('A'.($highCell + $line_count))->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A'.($highCell + $line_count))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            !$MPD_wrote = true;
        }
        if($type == 'MPD Report')//mpd errors will be displayed with a different color from the one of segment errors
        {
            if($stripped_line!=null){
            if(($stripped_line[0] == 'Info') || ($stripped_line[0] =='Information'))
            {
               $sheet->getStyle('B'.($highCell + $line_count))->getFont()->getColor()->setRGB('0000FF');
            }
            elseif (($stripped_line[0] == 'Warning') || ($stripped_line[0] =='WARNING')) 
            {
                //$sheet->getStyle('B'.($highCell + $line_count))->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKYELLOW);
               $sheet->getStyle('B'.($highCell + $line_count))->getFont()->getColor()->setRGB('FF7F50');
            }
            else
            {
                $sheet->getStyle('B'.($highCell + $line_count))->getFont()->getColor()->setRGB('FF0000');
            }
        }
        }
        else
        {
            if($stripped_line!=null){
            if(($stripped_line[0] == 'Info') || ($stripped_line[0] =='Information')|| ($next == true))
            {
                $sheet->getStyle('B'.($highCell + $line_count))->getFont()->getColor()->setRGB('0000FF');
                $next = false;
                if((substr($line, -1)==":") || (end($stripped_line)== 'with'))
                {
                    $next = true;
                }
            }
            elseif (($stripped_line[0] == 'Warning') || ($stripped_line[0] =='WARNING')) 
            {
                //$sheet->getStyle('B'.($highCell + $line_count))->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKYELLOW);
               $sheet->getStyle('B'.($highCell + $line_count))->getFont()->getColor()->setRGB('FF7F50');
            }
            else
            {
                foreach($stripped_line as $word){
                    if($word=="error"){
                        $error_counter+=1;
                    }
                }

                if($error_counter!=0){
                $sheet->getStyle('B'.($highCell + $line_count))->getFont()->getColor()->setRGB('DC143C');
                }
                else{
                $sheet->getStyle('B'.($highCell + $line_count))->getFont()->getColor()->setRGB('0000FF');    
                }
            }
        }
        }
        $line_count++;
    }
  } else{
      if(!$MPD_wrote)
        {
            $sheet->setCellValue('A'.($highCell + $line_count), $mpdURL);
            $sheet->setCellValue('B'.($highCell + $line_count), " ");
            $sheet->getCell('A'.($highCell + $line_count))->getHyperlink()->setUrl($mpdURL);
            $sheet->getStyle('A'.($highCell + $line_count))->getFont()->getColor()->setARGB('FF0000FF');
            $sheet->getStyle('A'.($highCell + $line_count))->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A'.($highCell + $line_count))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            !$MPD_wrote = true;
        } 
        }

}

function create_initial_spreadsheet() // create an initial spreadsheet and then read from it and write the data
{
    if(!file_exists('TestReport.xlsx'))
    {
    
        $spreadsheet = new Spreadsheet(); 
        $spreadsheet->setActiveSheetIndex(0)
        ->setCellValue('B1', 'MPD + Segment Report')
        ->setCellValue('A1', 'MPD');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $file_name = 'TestReport.xlsx';
        $writer->save($file_name);   
        
    }
}
?>
