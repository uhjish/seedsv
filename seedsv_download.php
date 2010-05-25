<?php

header('Content-Type: text/force-download');
header("Content-Disposition: attachment; filename=filtered_data.csv");

function parseFilterText( $fCol, $fColTexts){
    $fString = " AND ( (";
    $fTextArr = explode(';', $fColTexts);
    for ($idx=0; $idx < count($fTextArr); $idx++){
        $fText = $fTextArr[$idx];
	    if (substr($fText,0,1)=="[" && substr($fText,-1,1)=="]"){
	        //numeric range
	        $fText = substr($fText,1,-1);
	        list($min, $max) = explode(",",$fText);
	        if ($min != "." && $min != "" && is_numeric($min)){
	            $fString .= " ".$fCol." >= ".$min;
	        }
	        if ($max != "." && $max != "" && is_numeric($max)){
	            $fString .= " AND ".$fCol." <= ".$max;
	        }
	    }elseif (substr($fText,0,1)=="{" && substr($fText,-1,1)=="}") {
	        //multiple strings
	        $fText = substr($fText,1,-1);
	        $possibles = explode(",",$fText);
	        $fString .= " ($fCol LIKE '%".join("%' OR $fCol LIKE '%",$possibles)."%')";  
	        
	    }else{
	        //text regex
	        $fString .= " ".$fCol." LIKE '%".$fText."%'";
	    }
        if ($idx < count($fTextArr)-1){
            $fString .= " ) OR ( ";
        }else{
            $fString .= " ) ) ";
        }
    }
    return $fString;
}


if (isset($_REQUEST['firescope_grid'])) {
	try {
		$dbh = new PDO("sqlite:".$_REQUEST['db']);
                $table = $_REQUEST['table'];
                $colproperties = $dbh->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_NUM);
                $colnames = array();
                $output = "";
                foreach ($colproperties as $proprow) {
                    $colnames[ $proprow[0] ] = $proprow[1];
                }

                ob_clean();

		$sql_pref = "SELECT * FROM ".$table." WHERE 1 ";
                $sql_query = " ";
                if ($_REQUEST['firescope_grid_filterCol']!='' && strlen($_REQUEST['firescope_grid_filterText']) > 0) {
                    //break up the columns
                    $filterCols = $_REQUEST['firescope_grid_filterCol'];
                    $filterTextStr = $_REQUEST['firescope_grid_filterText'];
                    if (substr($filterCols,-1,1)=="|"){
                        $filterCols = substr($filterCols,0,-1);
                        $filterTextStr = substr($filterTextStr,0,-1);
                    }
                    $filterCols = explode('|',$filterCols);
                    $filterTexts = explode('|', $filterTextStr);
                    for ($curCol = 0; $curCol < count($filterCols); $curCol++){
                        //$filterCol = $colnames[ $filterCols[$curCol] ];
                        list($filterCol,$filterText) =  explode(':',$filterTexts[$curCol]);
                        //$filterCol = $colnames[ $filterCol ];
                        
                        $sql_query.= parseFilterText( $filterCol, $filterText );
                    }
                }

                $sql_suff = '';

		if (isset($_REQUEST['firescope_grid_sortCol'])) {
                    $sql_suff.=" ORDER BY ".$colnames[$_REQUEST['firescope_grid_sortCol']]." ".$_REQUEST['firescope_grid_sortOrder'];
		}

		$offset = ($_REQUEST['firescope_grid_page'] - 1) * $_REQUEST['firescope_grid_rows'];

        $sql_suff.=" LIMIT ".$_REQUEST['firescope_grid_rows']." OFFSET ".$offset;

        $sql = $sql_pref.$sql_query.$sql_suff;

		$qry = $dbh->query($sql) or exit ($sql);

        trigger_error($sql);


		$rows = $qry->fetchAll();

        print join("\t",$colnames);
		foreach ($rows as $row) {
            print "\n".join("\t",$row);
		}

		exit();

	} catch(PDOException $e) {
		echo $e->getMessage();
	}

}

?>
