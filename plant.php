<?php
#php red_listed_plants.php
#USDAendangered_usa_plants.txt row[1] = genus; row[2] = species
require_once("MDB2.php");

#connect to database=======
require_once("UniversalConnector.php");
 
// === Main database connection and error handling ===
$DB =& MDB2::connect($dsn);
if (PEAR::isError($DB)) { handleError($DB->getMessage()); }
$fp = fopen('forbAssociates_v2.txt', 'w');

# Open the File.
if (($handle = fopen("forbs.txt", "r")) !== FALSE) {
$output = '';
$outputtwo = '';

    while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) {
		$plant_genus = $data[2];
		$plant_species = $data[3];
		print $plant_genus . " " . $plant_species . "\n";
		#print $plant_genus . "\n";
		
			$value = plant_match($plant_genus,$plant_species);
			while ($row =& $value->fetchRow()){
					$insect_genus = $row[0];
					$insect_species = $row[1];
					$insect_tribe = $row[2];
					$insect_subfamily = $row[3];
					$insect_family = $row[4];
					$common_name = "not included in search";
					$plant_family = $row[7];
					$plant_species = $row[5];
					$plant_genus = $row[6];
					$matchType = "exact plant scientific name";
					$specimen_count = specimenCount($insect_genus,$insect_species,$plant_genus,$plant_species,$common_name,$exactMatch=TRUE);
					geoCoordinates($insect_genus,$insect_species);
				
				$output .= $plant_family . "\t" .  $plant_genus . "\t" .  $plant_species . "\t" . $insect_genus . "\t" . $insect_species . "\t" . $insect_tribe . "\t" .  $insect_subfamily . "\t" .  $insect_family . "\t" . $common_name . "\t" . $specimen_count . "\t" . $matchType . "\n";
				}
				
			$addValue = plant_fuzzyMatch($plant_genus);
			while ($row =& $addValue->fetchRow()){
				$insect_genus = $row[0];
				$insect_species = $row[1];
				$insect_tribe = $row[2];
				$insect_subfamily = $row[3];
				$insect_family = $row[4];
				$common_name = $row[5];
				$plant_family = $row[8];
				$plant_species = $row[6];
				$plant_genus = $row[7];
				$matchType = "plant genus and includes a common name";
				$specimen_count = specimenCount($insect_genus,$insect_species,$plant_genus,$plant_species,$common_name,$exactMatch=FALSE);	
			print $insect_genus . "\t" .  $insect_species . "\t" .  $plant_genus . "\t" .  $plant_species . "\t" .  $common_name . "\n";
				
				$outputtwo .= $plant_family . "\t" .  $plant_genus . "\t" .  $plant_species . "\t" . $insect_genus . "\t" . $insect_species . "\t" . $insect_tribe . "\t" .  $insect_subfamily . "\t" .  $insect_family . "\t" . $common_name . "\t" . $specimen_count . "\t" . $matchType . "\n";
				}

		}
		$header = "plantFamily" . "\t" .  "plantGenus" . "\t" .  "plantSpecies" . "\t" . "insectGenus" . "\t" . "insectSpecies" . "\t" .  "insectTribe" . "\t" .  "insectSubfamily" . "\t" .  "insectFamily" . "\t" . "commonName" . "\t" .  "specimenCount" . "\t" .  "matchType" . "\n" . $outputtwo . $output;
		
		fwrite($fp, $header);
			
    # Close the File.
    fclose($handle);
}

function geoCoordinates($insect_genus,$insect_species){
	#TODO write a separate geo file
}

function specimenCount($insect_genus,$insect_species,$plant_genus,$plant_species,$common_name,$typeLookup){
			global $DB;
			if ($typeLookup == TRUE){
				$hostSQL = "F2.HostTaxName = '$plant_species' and F1.HostTaxName='$plant_genus' and T1.TaxName='$insect_genus' and T2.TaxName='$insect_species'";
			}elseif($typeLookup == FALSE && trim($common_name) == TRUE){
				$hostSQL = "F1.HostTaxName='$plant_genus' and T1.TaxName='$insect_genus' and T2.TaxName='$insect_species' and HC.CommonName='$common_name'";
			}else{
			$hostSQL = "F1.HostTaxName='$plant_genus' and T1.TaxName='$insect_genus' and T2.TaxName='$insect_species'";
			}
			
			$resultsCount = $DB->query("Select count(distinct S1.SpecimenUID) from Specimen S1 left join MNL T1  ON S1.Genus = T1.MNLUID left join MNL T2  ON S1.Species = T2.MNLUID left join MNL T3 ON S1.Tribe=T3.MNLUID left join MNL T4 on S1.Subfamily=T4.MNLUID left join MNL T5 on T4.ParentID=T5.MNLUID left join Locality L1 on S1.Locality=L1.LocalityUID left join Flora_MNL F1 ON S1.HostG=F1.HostMNLUID left join Flora_MNL F2 ON S1.HostSp=F2.HostMNLUID left join Flora_MNL F3 ON S1.HostSSp=F3.HostMNLUID left join Flora_MNL F4 ON S1.HostF=F4.HostMNLUID left join SubDiv SD on L1.SubDivUID=SD.SubDivUID left join StateProv SP on SD.StateProvUID=SP.StateProvUID left join colevent CE on S1.ColEventUID=CE.ColEventUID left join Collector C1 on CE.Collector=C1.CollectorUID left join Country CN on SP.CountryUID=CN.UID left join HostCommonName HC on S1.HostCName=HC.CommonUID where $hostSQL");
				if (PEAR::isError($resultsCount)) {
					error_log("DB Error - Invalid query for collecting_counts");
					exit;
				}
				while ($row =& $resultsCount->fetchRow()){
					$counts = $row[0];
				}
				return $counts;
}
	
function plant_match($plant_genus,$plant_species){
		global $DB;
		$resultsGetName = $DB->query("select distinct T1.TaxName,T2.TaxName,T3.TaxName,T4.Taxname,T5.TaxName,F2.HostTaxName,F1.HostTaxName, F4.HostTaxName FROM Specimen S1 left join MNL T1 ON S1.Genus=T1.MNLUID left join MNL T2 ON S1.Species=T2.MNLUID left join MNL T3 ON S1.Tribe=T3.MNLUID left join MNL T4 on S1.Subfamily=T4.MNLUID left join MNL T5 on T4.ParentID=T5.MNLUID left join Locality L1 on S1.Locality=L1.LocalityUID left join Flora_MNL F1 ON S1.HostG=F1.HostMNLUID left join Flora_MNL F2 ON S1.HostSp=F2.HostMNLUID left join Flora_MNL F3 ON S1.HostSSp=F3.HostMNLUID left join Flora_MNL F4 ON S1.HostF=F4.HostMNLUID left join SubDiv SD on L1.SubDivUID=SD.SubDivUID left join StateProv SP on SD.StateProvUID=SP.StateProvUID left join colevent CE on S1.ColEventUID=CE.ColEventUID left join Collector C1 on CE.Collector=C1.CollectorUID left join Country CN on SP.CountryUID=CN.UID left join HostCommonName HC on S1.HostCName=HC.CommonUID  WHERE F2.HostTaxName = '$plant_species' and F1.HostTaxName='$plant_genus'");
		if (PEAR::isError($resultsGetName)) {
			error_log("DB Error - Invalid query for plant_match");
			exit;
		}
		return $resultsGetName;
	}
	
	function plant_fuzzyMatch($plant_genus){
			global $DB;
			$resultsGetName = $DB->query("select distinct T1.TaxName,T2.TaxName,T3.TaxName,T4.Taxname,T5.TaxName,HC.CommonName,F2.HostTaxName,F1.HostTaxName,F4.HostTaxName FROM Specimen S1 left join MNL T1 ON S1.Genus=T1.MNLUID left join MNL T2 ON S1.Species=T2.MNLUID left join MNL T3 ON S1.Tribe=T3.MNLUID left join MNL T4 on S1.Subfamily=T4.MNLUID left join MNL T5 on T4.ParentID=T5.MNLUID left join Locality L1 on S1.Locality=L1.LocalityUID left join Flora_MNL F1 ON S1.HostG=F1.HostMNLUID left join Flora_MNL F2 ON S1.HostSp=F2.HostMNLUID left join Flora_MNL F3 ON S1.HostSSp=F3.HostMNLUID left join Flora_MNL F4 ON S1.HostF=F4.HostMNLUID left join SubDiv SD on L1.SubDivUID=SD.SubDivUID left join StateProv SP on SD.StateProvUID=SP.StateProvUID left join colevent CE on S1.ColEventUID=CE.ColEventUID left join Collector C1 on CE.Collector=C1.CollectorUID left join Country CN on SP.CountryUID=CN.UID left join HostCommonName HC on S1.HostCName=HC.CommonUID WHERE F1.HostTaxName='$plant_genus' and (F2.HostTaxName is NULL or F2.HostTaxName='sp.')");
			if (PEAR::isError($resultsGetName)) {
				error_log("DB Error - Invalid query for plant_fuzzyMatch");
				exit;
			}
			return $resultsGetName;
		}	
		

// === disconnects from database ===  
$DB->disconnect();
?>