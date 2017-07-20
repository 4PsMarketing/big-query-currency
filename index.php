<?php
// Includes the autoloader for libraries installed with composer
require __DIR__ . '/vendor/autoload.php';

// Imports the Google Cloud client library
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\Core\ExponentialBackoff;

// Allow access from any site
header("Access-Control-Allow-Origin: *");

// Google Cloud Platform project ID
$projectId = 'datawarehouse4ps';

// Query we wish to run
$strSQL = "SELECT * FROM XRates.XRHistoryUSD ORDER BY Date DESC LIMIT 1";
$strCurrencyKey = "";

$arrReturn = array();
$arrReturn["CurrencyCode"] = "";
$arrReturn["ExchangeRate"] = "-1";

if (isset($_GET["cur"]))
{
	$strCurrencyKey = $_GET["cur"];
	$arrReturn["CurrencyCode"] = $strCurrencyKey;
}

// Currency Array
$arrCurrency = RunBigQuerySQL($projectId,$strSQL,false);

foreach($arrCurrency as $key => $val)
{
	// echo "Row key is " . $key . "<br/>";
	// print_r($val);

	if (isset($val[$strCurrencyKey]))
	{
		$arrReturn["ExchangeRate"] = $val[$strCurrencyKey];
	}
	
}

$objJSON = json_encode($arrReturn);
echo $objJSON;

/* 
* Function RunBigQuerySQL
*
* Parameters :
*
* $projectId - The id of the Google Cloud Project
*
* $query - The SQL to run
*
* $useLegacySql - Whether Legacy SQL should be used
*
* &$arrResults - pointer to array to populate with results
*
* Based on the Google PHP function run_query_as_job
*/
function RunBigQuerySQL($projectId, $query, $useLegacySql)
{
	$arrReturn = array();
	$bigQuery = new BigQueryClient([
        'projectId' => $projectId,
    ]);

    $job = $bigQuery->runQueryAsJob(
        $query,
        ['jobConfig' => ['useLegacySql' => $useLegacySql]]);
    
    // Creates a back off to allow the job to run, i.e. we wait for 500ms for it to run
    $backoff = new ExponentialBackoff(10);
    $backoff->execute(function () use ($job) 
    {
        $job->reload();
        if (!$job->isComplete()) {
            throw new Exception('Job has not yet completed', 500);
        }
    });

    // Get query results
    $queryResults = $job->queryResults();

    // Make sure query is complete
    if ($queryResults->isComplete()) 
    {
        $i = 0;
        $rows = $queryResults->rows();
        $arrReturn = $rows;
        //foreach ($rows as $row) 
        //{
            // Push the row to our return array
        //    array_push($arrResults,$row);

        //}
      
    } 
    else 
    {
        throw new Exception('The query failed to complete');
    }

    return($arrReturn);
}