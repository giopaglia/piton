<?php
/**
 * The old main file, for reference
 */
chdir(dirname(__FILE__));
ini_set('xdebug.halt_level', E_WARNING|E_NOTICE|E_USER_WARNING|E_USER_NOTICE);
ini_set('memory_limit', '1024M'); // or you could use 1G
ini_set('max_execution_time', 3000);
set_time_limit(3000);

include "lib.php";
include "local-lib.php";

include "DBFit.php";

/******************************************************************************
*                                                                             *
*                              Here I test stuff                              *
*                                                                             *
*******************************************************************************/
$numOptimizations = 2;
$numFolds = 5;
$minNo = 2;
// foreach([2,10] as $numOptimizations)
// foreach([3,10] as $numFolds)
// // foreach([2,5] as $minNo)
{
  echo "PARAMETERS: ($numOptimizations, $numFolds, $minNo)" . PHP_EOL;
  echo "numOptimizations: $numOptimizations" . PHP_EOL;
  echo "numFolds: $numFolds" . PHP_EOL;
  echo "minNo: $minNo" . PHP_EOL;

  $lr = new PRip(NULL);
  $lr->setNumOptimizations($numOptimizations);
  $lr->setNumFolds($numFolds);
  $lr->setMinNo($minNo);
  testMed($lr);
}

/******************************************************************************/
/******************************************************************************/
/******************************************************************************/
/******************************************************************************/

function testMed($lr) {
  TODO separate InputDBConnection and OutputDBConnection
  $db = getDBConnection();

  $db_fit = new DBFit($db);

  $db_fit->setTrainingMode([.8, .2]);
  $db_fit->setCutOffValue(0.10);
  $db_fit->setLearner($lr);
  $db_fit->setDefaultOption("textLanguage", "it");
  // $db_fit->setDefaultOption("textTreatment", ["BinaryBagOfWords", 10]);
  
  $db_fit->setInputTables([
    "Referti"
  , ["Pazienti", "Pazienti.ID = Referti.ID_PAZIENTE", "LEFT JOIN"]
  , ["Anamnesi", "Anamnesi.ID_REFERTO = Referti.ID", "LEFT JOIN"]
  , ["Diagnosi", "Diagnosi.ID_REFERTO = Referti.ID", "LEFT JOIN"]
  , ["Densitometrie", "Densitometrie.ID_REFERTO = Referti.ID", "LEFT JOIN"]
  , ["RaccomandazioniTerapeutiche", ["RaccomandazioniTerapeutiche.ID_REFERTO = Referti.ID"], "LEFT JOIN"]
  ]);

  // $db_fit->setLimit(40);
  // $db_fit->setLimit(500);
  
  $db_fit->setWhereClauses([
    [
      "Referti.DATA_REFERTO BETWEEN '2018-09-01' AND '2020-08-31'"
    , "Pazienti.SESSO = 'F'"
    , "!ISNULL(Anamnesi.STATO_MENOPAUSALE)"
    , "DATEDIFF(Referti.DATA_REFERTO,Pazienti.DATA_NASCITA) / 365 >= 40"
    // END structural constraints
    
    // END begin constraints for manual cleaning
    , "Anamnesi.BMI is NOT NULL"
    , "Anamnesi.BMI != -1"

    // Referti.ID NOT IN (SELECT ...)
    // , "FIND_IN_SET(Referti.ID, '495,1479,1481,2210') <= 0"
    , ["Referti.ID", "NOT IN", ["reuse_current_query", 1, ["!ISNULL(RaccomandazioniTerapeuticheUnitarie.TIPO)", "ISNULL(PrincipiAttivi.NOME)"]]]
    
    // Referti.ID NOT IN (SELECT ...)
    // , "FIND_IN_SET(Referti.ID, '153,155') <= 0"
    , ["Referti.ID", "NOT IN", ["reuse_current_query", 1, [], ["GROUP BY" => ["RaccomandazioniTerapeuticheUnitarie.TIPO", "PrincipiAttivi.NOME", "Referti.ID"], "HAVING" => "COUNT(*) > 1"]]]
    ],
    // [],
    // [
    //   // "FIND_IN_SET(RaccomandazioniTerapeuticheUnitarie.TIPO, 'Terapie osteoprotettive,Terapie ormonali') > 0",
    // ]
  ]);

  $db_fit->setOrderByClauses([["Referti.DATA_REFERTO", "ASC"]]);
  
  $db_fit->setIdentifierColumnName("Referti.ID");

  // echo $db_fit->showAvailableColumns(); die();
  
  // age
  $db_fit->addInputColumn(["DATEDIFF(Referti.DATA_REFERTO,Pazienti.DATA_NASCITA) / 365", NULL, "age"]);

  // bmi
  $db_fit->addInputColumn(["0+IF(ISNULL(Anamnesi.BMI) OR Anamnesi.BMI = -1, NULL, Anamnesi.BMI)", NULL, "body mass index"]);
  
  // gender
  $db_fit->addInputColumn(["Pazienti.SESSO", "ForceCategorical", "gender"]);
  // menopause state (if relevant)
  $db_fit->addInputColumn(["Anamnesi.STATO_MENOPAUSALE", NULL, "menopause state"]);
  // age at last menopause (if relevant)
  $db_fit->addInputColumn(["Anamnesi.ETA_MENOPAUSA", NULL, "age at last menopause"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.TERAPIA_STATO,'Mai'))", "ForceCategorical", "therapy status"]);
  // $db_fit->addInputColumn(["Anamnesi.TERAPIA_ANNI_SOSPENSIONE", NULL, "years of suspension"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(IF(Anamnesi.TERAPIA_COMPLIANCE,Anamnesi.TERAPIA_OSTEOPROTETTIVA_ORMONALE,'0'),'0'))", "ForceCategorical", "hormonal osteoprotective therapy"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(IF(Anamnesi.TERAPIA_COMPLIANCE,Anamnesi.TERAPIA_OSTEOPROTETTIVA_SPECIFICA,'0'),'0'))", "ForceCategorical", "specific osteoprotective therapy"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(IF(Anamnesi.TERAPIA_COMPLIANCE,Anamnesi.VITAMINA_D_TERAPIA_OSTEOPROTETTIVA,'0'),'0'))", "ForceCategorical", "vitamin D based osteoprotective therapy"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(IF(Anamnesi.TERAPIA_COMPLIANCE,Anamnesi.TERAPIA_ALTRO_CHECKBOX,'0'),'0'))", "ForceCategorical", "other osteoprotective therapy"]);
  // fragility fractures in spine (one or more)
  // checkbox+value("Anamnesi.FRATTURA_VERTEBRE_CHECKBOX" "Anamnesi.FRATTURA_VERTEBRE")
  // $db_fit->addInputColumn(["CONCAT('', IF(Anamnesi.FRATTURA_VERTEBRE_CHECKBOX, Anamnesi.FRATTURA_VERTEBRE, '0'))", "ForceCategorical", "Anamnesi.N_FRATTURE_VERTEBRE"]);
  $spinal_fractures = "CONCAT('', IF(ISNULL(Anamnesi.FRATTURA_VERTEBRE), '0', Anamnesi.FRATTURA_VERTEBRE))";
  $db_fit->addInputColumn([$spinal_fractures, "ForceCategorical", "vertebral fractures"]);
  // fragility fractures in hip (one or more)
  $femoral_fractures = "CONCAT('', IF(ISNULL(Anamnesi.FRATTURA_FEMORE), '0', Anamnesi.FRATTURA_FEMORE))";
  $db_fit->addInputColumn([$femoral_fractures, "ForceCategorical", "femoral fractures"]);
  // fragility fractures in other sites (one or more)
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.FRATTURA_SITI_DIVERSI,0))", "ForceCategorical", "fractures in other sites"]);
  // familiarity
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.FRATTURA_FAMILIARITA,0))", "ForceCategorical", "fracture familiarity"]);
  // current smoker
  // checkbox+value("Anamnesi.ABUSO_FUMO_CHECKBOX" "Anamnesi.ABUSO_FUMO")
  $db_fit->addInputColumn(["CONCAT('', IF(ISNULL(Anamnesi.ABUSO_FUMO_CHECKBOX),'No',IF(Anamnesi.ABUSO_FUMO_CHECKBOX, Anamnesi.ABUSO_FUMO, 'No')))", "ForceCategorical", "smoking habits"]);
  // alcol abuse
  // checkbox+value("Anamnesi.ALCOL_CHECKBOX" "Anamnesi.ALCOL")
  $db_fit->addInputColumn(["CONCAT('', IF(ISNULL(Anamnesi.ALCOL_CHECKBOX),NULL,IF(Anamnesi.ALCOL_CHECKBOX, Anamnesi.ALCOL, 'No')))", "ForceCategorical", "alcohol intake"]);
  // current corticosteoroid use
  // checkbox+value("Anamnesi.USO_CORTISONE_CHECKBOX" "Anamnesi.USO_CORTISONE")
  $db_fit->addInputColumn(["CONCAT('', IF(ISNULL(Anamnesi.USO_CORTISONE_CHECKBOX),'No',IF(Anamnesi.USO_CORTISONE_CHECKBOX, Anamnesi.USO_CORTISONE, 'No')))", "ForceCategorical", "cortisone"]);
  // current illnesses
  // $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.MALATTIE_ATTUALI_ALTRE_CONNETTIVITI,0))", "ForceCategorical", "other connective tissue diseases"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.MALATTIE_ATTUALI_ARTRITE_REUM,0))", "ForceCategorical", "rheumatoid arthritis"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.MALATTIE_ATTUALI_ARTRITE_PSOR,0))", "ForceCategorical", "psoriatic arthritis"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.MALATTIE_ATTUALI_LUPUS,0))", "ForceCategorical", "systemic lupus"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.MALATTIE_ATTUALI_SCLERODERMIA,0))", "ForceCategorical", "scleroderma"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.MALATTIE_ATTUALI_ALTRE_CONNETTIVITI,0))", "ForceCategorical", "other connective tissue diseases"]);

  // secondary causes
  $val_map = [
    "Diabete-insulino dipendente" => "diabetes mellitus",
    // "M.I.C.I." => "inflammatory bowel disease",
    // "Malattia cronica epatica come cirrosi/epatite cronica" => "chronic liver diseases",
    "Menopausa prematura" => "early menopause",
    "Malnutrizione cronica" => "chronic malnutrition",
    "Osteogenesi imperfecta in età adulta" => "adult osteogenesis imperfecta",
    "Ipertiroidismo non trattato per lungo tempo" => "untreated chronic hyperthyroidism",
  ];
  $ignore_vals = ["Malattia cronica epatica come cirrosi/epatite cronica", "M.I.C.I."];
  $db_fit->addInputColumn(["Anamnesi.CAUSE_OSTEOPOROSI_SECONDARIA", ["ForceCategoricalBinary", function ($input) use ($val_map, $ignore_vals) {
      if ($input === NULL) {
        return NULL;
      }
      $input = trim($input);
      if ($input === "NULL") {
        return NULL;
      }
      $rawValues = preg_split("/[\n\r]+/", $input);
      $values = [];
      foreach($rawValues as $value) {
        if (isset($val_map[$value])) {
          $values[] = $val_map[$value];
        } else if (!in_array($value, $ignore_vals)) {
          die_error("Unexpected value for Anamnesi.CAUSE_OSTEOPOROSI_SECONDARIA: \"$value\"");
        }
      }
      return $values;
      // var_dump($values);
      // Source: https://stackoverflow.com/a/4240153/5646732
      // return array_values(array_intersect_key($val_map, array_flip($rawValues)));
    }
  ], "secondary causes"]);
  // $db_fit->addInputColumn(["CONCAT('', IF(Anamnesi.CAUSE_OSTEOPOROSI_SECONDARIA = 'NULL' OR ISNULL(Anamnesi.CAUSE_OSTEOPOROSI_SECONDARIA),0,1))", "ForceCategorical", "Secondary causes"]);

  $db_fit->addInputColumn(["CONCAT('', IF(LOCATE(\"M.I.C.I.\",Anamnesi.CAUSE_OSTEOPOROSI_SECONDARIA)>0,1,COALESCE(Anamnesi.MICI,0)))", "ForceCategorical", "inflammatory bowel disease"]);

  $db_fit->addInputColumn(["CONCAT('', IF(LOCATE(\"Malattia cronica epatica come cirrosi/epatite cronica\",Anamnesi.CAUSE_OSTEOPOROSI_SECONDARIA)>0,1,COALESCE(Anamnesi.PATOLOGIA_EPATICA,0)))", "ForceCategorical", "chronic liver diseases"]);


  // $db_fit->addInputColumn(["CONCAT('', IF(!ISNULL(Diagnosi.COLONNA_NON_ANALIZZABILE) AND !ISNULL(Diagnosi.VERTEBRE_NON_ANALIZZATE_CHECKBOX),IF(Diagnosi.COLONNA_NON_ANALIZZABILE,'No',IF(Diagnosi.VERTEBRE_NON_ANALIZZATE_CHECKBOX,'Parzialmente','Tutta')),NULL))", "ForceCategorical", "portion of analyzed spine"]);
  // $db_fit->addInputColumn(["Diagnosi.COLONNA_VALORI_SUPERIORI", NULL, "spine with too high density values"]);
  // $db_fit->addInputColumn(["CONCAT('', IF(ISNULL(Diagnosi.FEMORE_NON_ANALIZZABILE),NULL,IF(Diagnosi.FEMORE_NON_ANALIZZABILE,'0','1')))", "ForceCategorical", "femur is analyzed"]);
  


  // FRAX
  // nullif(Diagnosi.FRAX_APPLICABILE, checkbox+value("Diagnosi.FRAX_PERCENTUALE" "Diagnosi.FRAX_FRATTURE_MAGGIORI" true))
  // $db_fit->addInputColumn(["CONCAT('', COALESCE(Diagnosi.FRAX_APPLICABILE,0))", "ForceCategorical", "FRAX is applicable"]);
  $db_fit->addInputColumn(["IF(Diagnosi.FRAX_APPLICABILE,0+IF(ISNULL(Diagnosi.FRAX_PERCENTUALE),NULL,IF(Diagnosi.FRAX_PERCENTUALE OR Diagnosi.FRAX_FRATTURE_MAGGIORI < 0.1, 0, Diagnosi.FRAX_FRATTURE_MAGGIORI)),NULL)", NULL, "FRAX (major fractures)"]);
  // nullif(Diagnosi.FRAX_APPLICABILE, checkbox+value("Diagnosi.FRAX_COLLO_FEMORE_PERCENTUALE" "Diagnosi.FRAX_COLLO_FEMORE" true))
  $db_fit->addInputColumn(["IF(Diagnosi.FRAX_APPLICABILE,0+IF(ISNULL(Diagnosi.FRAX_COLLO_FEMORE_PERCENTUALE),NULL,IF(Diagnosi.FRAX_COLLO_FEMORE_PERCENTUALE OR Diagnosi.FRAX_COLLO_FEMORE < 0.1, 0, Diagnosi.FRAX_COLLO_FEMORE)),NULL)", NULL, "FRAX (femur)"]);

  // DeFRA
  // map(["Diagnosi.DEFRA" => true, "Diagnosi.DEFRA_PERCENTUALE_01" => 0, "Diagnosi.DEFRA_PERCENTUALE_50" => 50])
  // TODO this query should work, but these field can be misused, so it's a good idea to recheck every now and then.
  // $db_fit->addInputColumn(["CONCAT('', COALESCE(Diagnosi.DEFRA_APPLICABILE,0))", "ForceCategorical", "DeFRA is applicable"]);
  $db_fit->addInputColumn(["IF(Diagnosi.DEFRA_APPLICABILE,0+IF(ISNULL(Diagnosi.DEFRA_PERCENTUALE_01),NULL,IF((Diagnosi.DEFRA_PERCENTUALE_01 OR Diagnosi.DEFRA < 0.1) AND Diagnosi.DEFRA_PERCENTUALE_50 = 0, 0,IF(ISNULL(Diagnosi.DEFRA_PERCENTUALE_50),NULL,IF(Diagnosi.DEFRA_PERCENTUALE_50 OR Diagnosi.DEFRA > 50, 50, Diagnosi.DEFRA)))),NULL)", NULL, "DeFRA"]);

  // FRAX_AGGIUSTATO
  // $db_fit->addInputColumn(["IF(Diagnosi.FRAX_AGGIUSTATO_APPLICABILE,0+IF(Diagnosi.FRAX_AGGIUSTATO_PERCENTUALE, 0, FRAX_FRATTURE_MAGGIORI_AGGIUSTATO_VALORE),NULL)", NULL, "Diagnosi.ALG_FRAX_AGG_FRATTURE"]);
  // $db_fit->addInputColumn(["IF(Diagnosi.FRAX_AGGIUSTATO_APPLICABILE,0+IF(Diagnosi.FRAX_COLLO_FEMORE_AGGIUSTATO_PERCENTUALE, 0, FRAX_COLLO_FEMORE_AGGIUSTATO_VALORE),NULL)", NULL, "Diagnosi.ALG_FRAX_AGG_FEMORE"]);

  // TBS
  // $db_fit->addInputColumn(["IF(Diagnosi.TBS_COLONNA_APPLICABILE,0+IF(Diagnosi.TBS_COLONNA_PERCENTUALE, 0, TBS_COLONNA_VALORE),NULL)", NULL, "Diagnosi.ALG_TBS"]);


  // clinical information (20 fields)
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.PATOLOGIE_UTERINE_CHECKBOX,0))", "ForceCategorical", "endometrial pathologies"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.NEOPLASIA_CHECKBOX,0))", "ForceCategorical", "breast cancer"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.SINTOMI_VASOMOTORI,0))", "ForceCategorical", "vasomotor symptoms"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.SINTOMI_DISTROFICI,0))", "ForceCategorical", "distrofic symptoms"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.DISLIPIDEMIA_CHECKBOX,0))", "ForceCategorical", "dyslipidemia"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.IPERTENSIONE,0))", "ForceCategorical", "hypertension"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.RISCHIO_TEV,0))", "ForceCategorical", "venous thromboembolism risk factors"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.PATOLOGIA_CARDIACA,0))", "ForceCategorical", "cardiac pathologies"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.PATOLOGIA_VASCOLARE,0))", "ForceCategorical", "vascular pathologies"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.INSUFFICIENZA_RENALE,0))", "ForceCategorical", "kidney failure"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.PATOLOGIA_RESPIRATORIA,0))", "ForceCategorical", "respiratory pathologies"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.PATOLOGIA_CAVO_ORALE_CHECKBOX,0))", "ForceCategorical", "oral pathologies"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.PAROLOGIA_ESOFAGEA,0))", "ForceCategorical", "esophageal pathologies"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.GASTRO_DUODENITE,0))", "ForceCategorical", "gastroduodenitis"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.GASTRO_RESEZIONE,0))", "ForceCategorical", "gastrectomy"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.RESEZIONE_INTESTINALE,0))", "ForceCategorical", "bowel resection"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.ALTRE_PATOLOGIE_CHECKBOX,0))", "ForceCategorical", "other diseases"]);
  // $db_fit->addInputColumn(["0+COALESCE(Anamnesi.VITAMINA_D,0)", NULL, "vitamin D-25OH"]);
  $db_fit->addInputColumn(["Anamnesi.VITAMINA_D", NULL, "vitamin D-25OH"]);
  // previous DXA spine total T score
  $db_fit->addInputColumn(["Anamnesi.COLONNA_T_SCORE", NULL, "previous spine T-score"]);
  // previous DXA spine total Z score
  $db_fit->addInputColumn(["Anamnesi.COLONNA_Z_SCORE", NULL, "previous spine Z-score"]);
  // previous DXA hip total T score
  $db_fit->addInputColumn(["Anamnesi.FEMORE_T_SCORE", NULL, "previous neck T-score"]);
  // previous DXA hip total Z score
  $db_fit->addInputColumn(["Anamnesi.FEMORE_Z_SCORE", NULL, "previous neck Z-score"]);



  $db_fit->addInputColumn(["CONCAT('', IF($spinal_fractures != '0' OR $femoral_fractures != '0','1',Diagnosi.OSTEOPOROSI_GRAVE))", "ForceCategorical", "severe osteoporosis"]);

  // hip (normal, osteopenic, osteoporotic)
  // merge(checkbox+value(Diagnosi.SITUAZIONE_FEMORE_SN...),checkbox+value(SITUAZIONE_FEMORE_DX...))
  $db_fit->addInputColumn(["CONCAT('', IF(Diagnosi.SITUAZIONE_FEMORE_SN_CHECKBOX, Diagnosi.SITUAZIONE_FEMORE_SN, IF(Diagnosi.SITUAZIONE_FEMORE_DX_CHECKBOX, Diagnosi.SITUAZIONE_FEMORE_DX, NULL)))", "ForceCategorical", "femur status"]);

  // spine (normal, osteopenic, osteoporotic)
  // checkbox+value("Diagnosi.SITUAZIONE_COLONNA_CHECKBOX" "Diagnosi.SITUAZIONE_COLONNA")
  // $db_fit->addInputColumn(["CONCAT('', IF(Diagnosi.SITUAZIONE_COLONNA_CHECKBOX, Diagnosi.SITUAZIONE_COLONNA, 'Normale'))", "ForceCategorical", "Diagnosi.N_SITUAZIONE_COLONNA"]);
  $db_fit->addInputColumn(["Diagnosi.SITUAZIONE_COLONNA", "ForceCategorical", "spine status"]);
  
  // BMD
  // $db_fit->addInputColumn(["Densitometrie.NECK_BMD", NULL, "neck BMD"]);
  // $db_fit->addInputColumn(["Densitometrie.TOT_BMD", NULL, "spine BMD"]);

  // current DXA spine total T score
  $db_fit->addInputColumn(["Densitometrie.TOT_T_SCORE", NULL, "spine T-score"]);
  // current DXA spine total Z score
  $db_fit->addInputColumn(["Densitometrie.TOT_Z_SCORE", NULL, "spine Z-score"]);
  // current DXA hip total T score
  $db_fit->addInputColumn(["Densitometrie.NECK_T_SCORE", NULL, "neck T-score"]);
  // current DXA hip total Z score
  $db_fit->addInputColumn(["Densitometrie.NECK_Z_SCORE", NULL, "neck Z-score"]);

  $db_fit->setOutputColumns([
    ["RaccomandazioniTerapeuticheUnitarie.TIPO",
      [
        ["RaccomandazioniTerapeuticheUnitarie", ["RaccomandazioniTerapeuticheUnitarie.ID_RACCOMANDAZIONE_TERAPEUTICA = RaccomandazioniTerapeutiche.ID"
        , "RaccomandazioniTerapeuticheUnitarie.TIPO != 'Indagini approfondimento'"], "LEFT JOIN"]
      ],
      "ForceCategoricalBinary",
      "Terapia"
    ],
    [
      // "CONCAT(PrincipiAttivi.NOME, IF(!STRCMP(PrincipiAttivi.QUANTITA, 'NULL') || ISNULL(PrincipiAttivi.QUANTITA), '', CONCAT(' ', PrincipiAttivi.QUANTITA)))",
      "PrincipiAttivi.NOME",
      [
        ["ElementiTerapici", ["ElementiTerapici.ID_RACCOMANDAZIONE_TERAPEUTICA_UNITARIA = RaccomandazioniTerapeuticheUnitarie.ID"], "LEFT JOIN"],
        ["PrincipiAttivi", "ElementiTerapici.ID_PRINCIPIO_ATTIVO = PrincipiAttivi.ID", "LEFT JOIN"]
      ],
      "ForceCategoricalBinary",
      "PrincipioAttivo"
    ]
  ]);

  // Set globalNodeOrder (to match the output tables and results with those in the paper)
  $globalNodeOrder = ["Terapie ormonali", "Terapie osteoprotettive", "Vitamina D terapia", "Vitamina D Supplementazione", "Calcio supplementazione", "Alendronato", "Denosumab", "Risedronato", "Calcifediolo", "Colecalciferolo", "Calcio citrato", "Calcio carbonato"];
  $db_fit->setGlobalNodeOrder($globalNodeOrder);

  // Launch training
  $start = microtime(TRUE);
  $db_fit->updateModel();
  $end = microtime(TRUE);
  echo "updateModel took " . ($end - $start) . " seconds to complete." . PHP_EOL;
  
  // List trained models
  echo "AVAILABLE MODELS:" . PHP_EOL;
  $db_fit->listAvailableModels();

  // Print a few relevant tables
  if ($db_fit->getIdentifierColumnName() !== NULL) {
    $cmp_classes = function ($a, $b) use ($globalNodeOrder)
    {
      deprefixify($a, "NO_");
      deprefixify($b, "NO_");

      $x = array_search($a, $globalNodeOrder);
      $y = array_search($b, $globalNodeOrder);
      if ($x === false && $y === false) {
        warn("Nodes not found in globalNodeOrder array: " . PHP_EOL . get_var_dump($a) . PHP_EOL . get_var_dump($b) . PHP_EOL . get_var_dump($globalNodeOrder));
        return 0;
      }
      else if ($x === false) {
        warn("Node not found in globalNodeOrder array: " . PHP_EOL . get_var_dump($a) . PHP_EOL . get_var_dump($globalNodeOrder));
        return 1;
      }
      else if ($y === false) {
        warn("Node not found in globalNodeOrder array: " . PHP_EOL . get_var_dump($b) . PHP_EOL . get_var_dump($globalNodeOrder));
        return -1;
      }
      return $x-$y;
    };

    $compute_set_name = function (array $probs_res) use ($cmp_classes) {
      $new_arr = [];
      $name_map = [
        "Calcio supplementazione" => "calsup",
        "Terapie osteoprotettive" => "osteop",
        "Vitamina D Supplementazione" => "vitDsup",
        "NO_Calcio supplementazione" => "NO_calcio",
        "NO_Terapie osteoprotettive" => "NO_osteop",
        "NO_Vitamina D Supplementazione" => "NO_vitDsup",
        "Alendronato" => "ale",
        "Denosumab" => "den",
        "Risedronato" => "ris",
        "NO_Alendronato" => "NO_ale",
        "NO_Denosumab" => "NO_den",
        "NO_Risedronato" => "NO_ris",
        "Calcifediolo" => "calci",
        "NO_Calcifediolo" => "NO_calci",
        "Colecalciferolo" => "colec",
        "NO_Colecalciferolo" => "NO_colec",
        "Calcio citrato" => "citr",
        "NO_Calcio citrato" => "NO_citr",
        "Calcio carbonato" => "carb",
        "NO_Calcio carbonato" => "NO_carb",
      ];
      foreach($probs_res as $prob_name => $val) {
        // $boolval = !startsWith($val, "NO_");
        $new_arr[$prob_name] = $name_map[$val];
      }
      uksort($new_arr, $cmp_classes);
      return join("\n", $new_arr);
    };

    function cmp_class_names($x, $y) {
      $a = substr_count($x, "\n");
      $b = substr_count($y, "\n");
      if ($a != $b) {
        return ($a < $b) ? -1 : 1;
      }

      $a = substr_count($x, "NO_");
      $b = substr_count($y, "NO_");
      if ($a != $b) {
        return ($a < $b) ? -1 : 1;
      }

      $x = explode("\n", $x);
      $y = explode("\n", $y);
      $x = array_map(function ($c) { return (startsWith($c, "NO_") ? 0 : 1); }, $x);
      $y = array_map(function ($c) { return (startsWith($c, "NO_") ? 0 : 1); }, $y);
      $x = intval(join("", $x), 2);
      $y = intval(join("", $y), 2);

      return $y-$x;
    }

    function printConfusionMatrix($cm, string $cm_name, bool $print_relative = true, bool $print_empty_rows = true, bool $prettyPrintSetCM = false, bool $print_totals = true) {
      postfixisify($cm_name, ".csv");
      $f = fopen($cm_name, "w");

      $display_class_name = function ($class_name) use ($prettyPrintSetCM) {
        if ($prettyPrintSetCM) {
          // Ignore "NO_*" pieces
          $class_name = explode("\n", $class_name);
          $class_name = array_filter($class_name, function($v) { return !startsWith($v, "NO_"); });
          $class_name = join("\n", $class_name);
          if ($class_name == "") {
            $class_name = "∅";
          }
        }
        return $class_name;
      };

      // Get class names
      $class_names = [];
      foreach($cm as $gt_class => $row) {
        $class_names[] = $gt_class;
        foreach($row as $pr_class => $count) {
          $class_names[] = $pr_class;
        }
      }
      $class_names = array_unique($class_names);

      // Fill empty cells with 0s
      foreach ($class_names as $class_name1) {
        foreach ($class_names as $class_name2) {
          if(!isset($cm[$class_name1][$class_name2])) {
            $cm[$class_name1][$class_name2] = 0;
          }
        }
      }

      // Find correct class ordering
      usort($class_names, "cmp_class_names");

      // Print HTML table header for $class_names;
      $out = "";
      $out .= "<table class='blueTable' style='border-collapse: collapse; ' border='1'>";
      $out .= "<thead>";
      $out .= "<tr>";
      $csv_row = [];
      $out .= "<th style='width:30px'>#</th>";
      $csv_row[] = "#";
      foreach ($class_names as $class_name) {
        $out .= "<th>" . str_replace("\n", "<br>", $display_class_name($class_name)) . "</th>";
        $csv_row[] = $display_class_name($class_name);
      }
      if ($print_totals) {
        $out .= "<th>TOT</th>";
        $csv_row[] = "TOT";
      }
      fputcsv($f, $csv_row);
      $out .= "</tr>";
      $out .= "</thead>";

      // Print HTML table body
      $out .= "<tbody>";
      foreach ($class_names as $i => $gt_class_name) {
        $out_row = "";
        $out_row .= "<tr>";
        $csv_row = [];
        $out_row .= "<th>" . str_replace("\n", "<br>", $display_class_name($gt_class_name)) . "</th>";
        $csv_row[] = $display_class_name($gt_class_name);
        $row = $cm[$gt_class_name];

        $row_tot = 0;
        foreach ($class_names as $j => $pr_class_name) {
          $row_tot += $row[$pr_class_name];
        }

        $empty_row = true;
        foreach ($class_names as $j => $pr_class_name) {
          $count = $row[$pr_class_name];
          if ($count != 0) {
            $empty_row = false;
          }
          if ($print_relative) {
            $count_str = $count != 0 ? "$count (" . (round(($row_tot == 0 ? 0 : $count/$row_tot), 2)*100) . "%)" : "";
          } else {
            $count_str = $count != 0 ? strval($count) : "";
          }
          $style_str = "";
          if ($i == $j) {
            $style_str = "style='background-color:rgba(225, 235, 52, 40)'";
          }
          $out_row .= "<td $style_str>$count_str</td>";
          $csv_row[] = $count_str;
        }
        if ($print_totals) {
          $out_row .= "<td>$row_tot</td>";
          $csv_row[] = $row_tot;
        }
        
        $out_row .= "</tr>";

        if (!$empty_row || $print_empty_rows) {
          $out .= $out_row;
          fputcsv($f, $csv_row);
        }
      }
      $out .= "</tbody>";
      $out .= "</table>";

      fclose($f);

      echo $out . PHP_EOL;
    }

    $predictionResults = $db_fit->getPredictionResults();

    // var_dump($predictionResults);
    // var_dump($db_fit->listHierarchyNodes());
    
    $print_relative = false;
    $print_empty_rows = false;

    foreach ($db_fit->listHierarchyNodes() as $nodeRp) {
      $node = $nodeRp[0];
      $recursionPath = $nodeRp[1];
      $recursionLevel = count($recursionPath);
      $classRecursionPath = array_column($recursionPath, 1);
      $classRecursionPath = array_merge($classRecursionPath, ["res"]);

      echo "<h1>" . $node["name"] . "</h1>" . PHP_EOL;
      // echo toString($classRecursionPath) . PHP_EOL;

      // Build local confusion matrix if the node is not the root
      if($recursionLevel != 0) {
        $confusionMatrix = [];
        $confusionMatrixRT = ["RA" => [], "RNA" => [], "NRA" => [], "NRNA" => []];

        foreach (arr_get_value($predictionResults, $classRecursionPath) as $instance_id => $res) {

          $gt_key = $res[0];
          $pr_key = $res[1];
          
          if (!isset($confusionMatrix[$gt_key][$pr_key])) {
            $confusionMatrix[$gt_key][$pr_key] = 0;
          }
          $confusionMatrix[$gt_key][$pr_key]++;

          if (!isset($confusionMatrixRT[$res[2]][$gt_key][$pr_key])) {
            foreach ($confusionMatrixRT as $rt => $cm) {
              $confusionMatrixRT[$rt][$gt_key][$pr_key] = 0;
            }
          }
          $confusionMatrixRT[$res[2]][$gt_key][$pr_key]++;
        }

        echo "<h2>Confusion Matrices</h2>" . PHP_EOL;
        printConfusionMatrix($confusionMatrix, safe_basename("cm_" . $node["name"]), $print_relative, $print_empty_rows);
        echo "<h2>By RuleType</h2>" . PHP_EOL;
        foreach ($confusionMatrixRT as $rt => $cmRT) {
          echo "<h3>Rule Type $rt</h3>" . PHP_EOL;
          printConfusionMatrix($cmRT, safe_basename("cmRT_" . $rt . "-" . $node["name"]), $print_relative, $print_empty_rows);
        }
      }

      // Build set confusion matrix if the node has children
      $childNodes = $db_fit->listHierarchyNodes($node, 1);
      // echo get_var_dump($node);
      // echo get_var_dump($childNodes);
      if (count($childNodes)) {
        $confusionMatrixSet = [];
        $confusionMatrixSetRT = ["RA" => [], "RNA" => [], "NRA" => [], "NRNA" => []];

        $instance_counted = [];
        foreach ($childNodes as $i_prob => $childnodeRp) {
          $childNode = $childnodeRp[0];
          $childRecursionPath = $childnodeRp[1];
          $childClassRecursionPath = array_column($childRecursionPath, 1);
          $childClassRecursionPath = array_merge($childClassRecursionPath, ["res"]);

          // echo get_var_dump($childClassRecursionPath);
          
          foreach (arr_get_value($predictionResults, $childClassRecursionPath) as $instance_id => $instance_result) {
            if (in_array($instance_id, $instance_counted)) {
              continue;
            }
            // Only consider instances that are in the test set of all children
            $instance_results = [];
            $ignore = false;
            foreach ($childNodes as $childnodeRp2) {
              $childNode2 = $childnodeRp2[0];
              $childRecursionPath2 = $childnodeRp2[1];
              $childClassRecursionPath2 = array_column($childRecursionPath2, 1);
              $childClassRecursionPath2 = array_merge($childClassRecursionPath2, ["res"]);
              $childRes2 = arr_get_value($predictionResults, $childClassRecursionPath2);
              if (!isset($childRes2[$instance_id])) {
                $ignore = true;
                break;
              }
              else {
                $instance_results[$childNode2["name"]] = $childRes2[$instance_id];
              }
            }
            if ($ignore) continue;

            $gtSet_key = $compute_set_name(array_column_assoc($instance_results,0));
            $prSet_key = $compute_set_name(array_column_assoc($instance_results,1));

            // echo get_var_dump(array_column_assoc($instance_results,0)) . PHP_EOL;
            // echo $compute_set_name(array_column_assoc($instance_results,0)) . PHP_EOL;

            if (!isset($confusionMatrixSet[$gtSet_key][$prSet_key])) {
              $confusionMatrixSet[$gtSet_key][$prSet_key] = 0;
            }
            $confusionMatrixSet[$gtSet_key][$prSet_key]++;

            // Avoid counting the same instance twice due in the same cell
            $cell_counted = [];
            $res_prev = NULL;
            foreach ($instance_results as $prob_name => $res) {
              if (in_array([$res[2], $gtSet_key, $prSet_key], $cell_counted)) {
                continue;
              }
              // By default we count if there is at least one rule of this type
              // With this we only count if all rules are of this type
              if ($res_prev !== NULL && $res_prev[2] != $res[2]) {
                break;
              }
              else {
                $res_prev = $res;
              }
              //
              if (!isset($confusionMatrixSetRT[$res[2]][$gtSet_key][$prSet_key])) {
                foreach ($confusionMatrixSetRT as $rt => $cm) {
                  $confusionMatrixSetRT[$rt][$gtSet_key][$prSet_key] = 0;
                }
              }
              $confusionMatrixSetRT[$res[2]][$gtSet_key][$prSet_key]++;

              $cell_counted[] = [$res[2], $gtSet_key, $prSet_key];
            }
            $instance_counted[] = $instance_id;
          }
        }

        echo "<h2>Set confusion Matrices</h2>" . PHP_EOL;
        printConfusionMatrix($confusionMatrixSet, safe_basename("cmSet_" . $node["name"]), $print_relative, $print_empty_rows, true);
        echo "<h2>By RuleType</h2>" . PHP_EOL;
        foreach ($confusionMatrixSetRT as $rt => $cm) {
          echo "<h3>Rule Type $rt</h3>" . PHP_EOL;
          printConfusionMatrix($cm, safe_basename("cmSetRT_" . $rt . "-" . $node["name"]), $print_relative, $print_empty_rows, true);
        }
      }
    }
  }
  
  return;

  // This is to test the prediction an a specific data entry
  if ($db_fit->getIdentifierColumnName() !== NULL) {
    $db_fit->predictByIdentifier(1);
  }

  // $db_fit->test_all_capabilities();
  // $db_fit->predictByIdentifier(15);
  // $db_fit->predictByIdentifier(1);
  // $db_fit->predictByIdentifier(2);
  // $db_fit->predictByIdentifier(9);
  // $db_fit->predictByIdentifier(3);
  
  var_dump($db_fit->getPredictionResults());
  echo PHP_EOL . toString($db_fit->getPredictionResults()) . PHP_EOL;
}

?>
