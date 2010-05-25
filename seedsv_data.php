<?php

$tbl_colors = array( "00"=>"#FCFAE8", "01"=>"#F4EDAB", "10"=>"#F8F3CA", "11"=>"#F0E68C" ); 


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
		$total = count($rows);

		//$rows = array_slice($rows, $offset, $_REQUEST['firescope_grid_rows']);
		// or use mysql_data_seek()
		// or add a `LIMIT $offset, $_REQUEST['firescope_grid_rows']` to your sql

		$output = '<table><tr>';
        foreach ($colnames as $col){
            $output .= '<th>'.$col.'</th>';
        }
        $rowidx = 0;
		foreach ($rows as $row) {
			$output .= '<tr>';
            $colidx = 0;
            foreach ($colnames as $col){
                $color_idx = (string)($rowidx%2) . (string)($colidx%2);
                $output .=	'<td bgcolor="'.$tbl_colors[$color_idx].'"><div class="scrollable">'.$row[$col].'</div></td>';
                $colidx++;
            }
		    $rowidx++;
		}

		$output .= '</table>';

		ob_clean();

                $sql_pref = "SELECT COUNT(*) FROM ".$table." WHERE 1";
                $sql = $sql_pref.$sql_query;
                $total = $dbh->query($sql)->fetch(PDO::FETCH_NUM);
                $total = $total[0];

                ob_clean();

		echo '<span id="firescope_grid_example_total" style="display:none">'.$total.'</span>';
		echo '<span id="firescope_grid_example_filterString">'.$filterTextStr.'</span>';
		echo '<span>'.$output.'</span>';
		exit();

	} catch(PDOException $e) {
		echo $e->getMessage();
	}

}

?>
