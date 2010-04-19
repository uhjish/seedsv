<?php
// get data file and separator
$int_type = 'INTEGER';
$float_type = 'REAL';
$string_type = 'TEXT';

$data_file_url = $_REQUEST['data'];
$sep = $_REQUEST['sep'];
$sep = str_replace('\\\\','\\',$sep);
$header = $_REQUEST['header'];
$persistent = (int)$_REQUEST['persistent'];

$working_dir = $_REQUEST['dir'];
//check if dir is writeable

$data_file = end(split('\/',$data_file_url));

$dbprefix = $data_file; 

if ($persistent != 1){
	$dbprefix = $dbprefix.time();//.$timeasinteger;
	set_time_limit(0);
}

$dbname = $working_dir.$dbprefix.'.sqlite';

//read the header line of the data
//      which MUST be the last line that starts with #

$file = fopen($data_file_url, "r") or exit("Unable to open file!");

echo '<html><head><title>s e e D S V - '.$data_file.'</title>';

echo <<<PAGEHEAD

<script type="text/javascript" src="jquery-1.2.6.js"></script>

<link type="text/css" rel="stylesheet" href="seedsv.css"/>
<script type="text/javascript" src="jquery.firescope_grid.js"></script>
</head>
<body> 
<div id="loadingDiv" name="loadingDiv">
<img src="resources/db_load.gif", name="dbload_img"/>
Creating database ...
PAGEHEAD;


//force all this loading stuff to the browser
flush_now();
#echo $file."\n";
echo $dbname."\n";
echo $header."\n";
echo $sep."\n";

if ($persistent != 1 || !file_exists($dbname)){
#	echo $file."\n";
#	echo $dbname."\n";
#	echo $header."\n";
#	echo $sep."\n";
	echo "\n Making db from file";
	makeDBfromFile($file, $dbname, $header,$sep);

}

echo '</div>';

echo '<a href="'.$data_file_url.'"><font size=-1>Download</font></a>';

echo <<<PAGETOP

<!-- container for the FireScope Grid //-->
<div id="firescope_grid_example"></div>

<!-- Initialize the FireScope Grid //-->
<script type="text/javascript">

document.getElementById("loadingDiv").style.visibility='hidden';
$(document).ready( function() {
	$('#firescope_grid_example').firescope_grid({
		rows: 10,
PAGETOP;

echo "url: 'seedsv_data.php?db=".$dbname."&table=data&firescope_grid=yarp', // your server side file";

echo  <<<PAGEBOTTOM
                filterCols: ['auto'],
		sortCols: ['auto'],
		sortCol: 0,
		navBarShow: 'always',
		navBarAlign: 'right',
		navBarLocation: 'top',
		data: {
			yourparm: 'ok'
		},
		ignore: null				
	});	
});
</script>
</body>
</html>

PAGEBOTTOM;

function flush_now(){
    echo(str_repeat(' ',256));
    // check that buffer is actually set before flushing
    if (ob_get_length()){           
        @ob_flush();
        @flush();
        @ob_end_flush();
    }   
    @ob_start();
}

function makeDBfromFile($file, $dbname, $header, $sep){

	ini_set("PHP_INI_USER","apache");
	$header_line = '';
	$line = '';
	$count = -1;
	while (!feof($file) && $count <= (int)$header ){
	    $line = fgets($file);
		if ($header=='' || $header=='-1'){
			break;
		}
	    if (is_numeric($header)){
	        if ($count <= (int)$header){
	            $header_line = trim($line);
	        }else{
	            break;
	        }
	    }else{
	        if (substr($line, 0, strlen($header)) == $header){
	            $header_line = trim(substr($line,1));
	        }else{
	            break;
	        }
	    }
	    $count++;
	}
	
	
	$header_line = str_replace(" ", "_", $header_line);
	$header_line = str_replace('"', "", $header_line);
	
	
	$ncols = 0;
	$colnames = array();
	$coltypes = array();
	$values = array();
    #echo $header_line;
    #echo "line: ".$line;
	
	$line = trim($line);
	if ($line == ''){
    
	    exit("Empty or malformed file! Cannot read the first data line!");
	}
	
	$colnames = preg_split('/'.$sep.'/', $header_line);
	$values = preg_split('/'.$sep.'/', $line);
	
	if (($header_line != '') && (count($colnames) != count($values))){
	    exit("Number of column names does not match number of values!".count($colnames).'  '.count($values));
	}
	
	$ncols = count($values);
	
	//exit ($header_line);
	
	for ($colNum = 0; $colNum < $ncols; $colNum++){
	    $cur = $values[$colNum];
	    //echo $cur;
	    if (is_numeric($cur)){
	        if ( (int)$cur == (float)$cur ){
	            $coltypes[$colNum] = $int_type;
	        }else{
	            $coltypes[$colNum] = $float_type;
	        }
	    }else{
	        $coltypes[$colNum] = $string_type;
	    }
	    
	    if ($header_line == ''){
	        //use default colnames
	        $colnames[$colNum] = "col".$colNum;
	    }    
	    
	}
	//exit ($line);
	
	
	
	$create_stmt = "create table data (";
	for ($colNum = 0; $colNum < $ncols; $colNum++){
	    $create_stmt .= ' '.$colnames[$colNum].' '.$coltypes[$colNum];
	    if ($colNum < $ncols-1){
	        $create_stmt .= ",";
	    }else{
	        $create_stmt .= ")";
	    }
	}
	try{
	    $dbh = new PDO("sqlite:".$dbname);
	}catch (PDOException $e){
	    exit ("Cannot access/create DB: ".$dbname."\n".$e);
	}
	$dbh->exec('DROP TABLE IF EXISTS "data"');
	$dbh->exec($create_stmt);
	$dbh->exec( 'begin transaction');
	$dbh->exec('insert into "data" values('."'".join("','",$values)."')");
	$st_time= time();
	while (!feof($file)){
	    $values = preg_split('/'.$sep.'/', trim(fgets($file)));
	    $dbh->exec('insert into "data" values('."'".join("','",$values)."')");
		//echo join(",",$values);
		$c_time = time();
		if (($c_time > $st_time) && ($c_time-$st_time) % 10 == 0){
			echo "&#00;";
			$st_time = time();
			flush_now();
		
		}
	}
	$dbh->exec('COMMIT TRANSACTION');
	
	
	fclose($file);
	        
}//end makeDBfromFile	     



