<?php

chdir(dirname(__FILE__));
ini_set('xdebug.halt_level', E_WARNING|E_NOTICE|E_USER_WARNING|E_USER_NOTICE);
ini_set('memory_limit', '1024M'); // or you could use 1G
ini_set('max_execution_time', 3000);
set_time_limit(3000);

include "lib.php";
include "local-lib.php";

include "DBFit.php";

/****************************************************
*                                                   *
*                 Here I test stuff                 *
*                                                   *
****************************************************/
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
  testMed3($lr);
}

exit();

$lr = new PRip();
$lr->setNumOptimizations(3);
testMed3($lr);
exit();

testMed2();
exit();
testMed();
exit();
testSPAM();
exit();
testSillyWithJoin();
exit();
testSilly();
testWinery();
exit();
testCovid();
exit();
testDiabetes();
exit();
exit();
echo "All good" . PHP_EOL;



function testMed3($lr) {
  $db = getDBConnection();

  $db_fit = new DBFit($db);
  $db_fit->setTrainingMode([.8, .2]);
  
  $db_fit->setCutOffValue(0.10);

  $db_fit->setInputTables([
    "Referti"
  , ["Pazienti", [
        "Pazienti.ID = Referti.ID_PAZIENTE"
      , "Pazienti.SESSO = 'F'"
      ], "LEFT JOIN"]
  , ["Anamnesi", "Anamnesi.ID_REFERTO = Referti.ID", "LEFT JOIN"]
  , ["Diagnosi", "Diagnosi.ID_REFERTO = Referti.ID", "LEFT JOIN"]
  , ["Densitometrie", "Densitometrie.ID_REFERTO = Referti.ID", "LEFT JOIN"]
  , ["RaccomandazioniTerapeutiche", ["RaccomandazioniTerapeutiche.ID_REFERTO = Referti.ID"], "LEFT JOIN"]
  ]);

  // $db_fit->setLimit(40);
  // $db_fit->setLimit(100);
  // $db_fit->setLimit(500);
  
  $db_fit->setWhereClauses([
    [
      "Referti.DATA_REFERTO BETWEEN '2018-09-01' AND '2020-08-31'"
    , "Anamnesi.BMI is NOT NULL"
    , "Anamnesi.BMI != -1"
    , "FIND_IN_SET(Referti.ID, '1395,1393,1297,2125,150,148') <= 0"
    ],
    [],
    [
      // "FIND_IN_SET(RaccomandazioniTerapeuticheUnitarie.TIPO, 'Terapie osteoprotettive,Terapie ormonali') > 0",
    ]
  ]);

  $db_fit->setOrderByClauses([["Referti.DATA_REFERTO", "ASC"]]);

  $db_fit->setLearner($lr);

  // $db_fit->setDefaultOption("textLanguage", "it");
  // $db_fit->setDefaultOption("textTreatment", ["BinaryBagOfWords", 10]);
  
  $db_fit->setIdentifierColumnName("Referti.ID");

  // TODO remove
  // $db_fit->addInputColumn(["Referti.ID"]);
  // $db_fit->addInputColumn(["Referti.DATA_REFERTO", "DaysSince"]);
  
  // gender
  $db_fit->addInputColumn(["Pazienti.SESSO", "ForceCategorical", "gender"]);
  // menopause state (if relevant)
  $db_fit->addInputColumn(["DATEDIFF(Referti.DATA_REFERTO,Pazienti.DATA_NASCITA) / 365", NULL, "age"]);
  $db_fit->addInputColumn(["Anamnesi.STATO_MENOPAUSALE", NULL, "menopause state"]);
  // age at last menopause (if relevant)
  $db_fit->addInputColumn(["Anamnesi.ETA_MENOPAUSA", NULL, "age at last menopause"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.TERAPIA_STATO,'Mai'))", "ForceCategorical", "therapy status"]);
  $db_fit->addInputColumn(["Anamnesi.TERAPIA_ANNI_SOSPENSIONE", NULL, "years of suspension"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.TERAPIA_OSTEOPROTETTIVA_ORMONALE,0))", "ForceCategorical", "hormonal osteoprotective therapy"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.TERAPIA_OSTEOPROTETTIVA_SPECIFICA,'0'))", "ForceCategorical", "specific osteoprotective therapy"]);
  $db_fit->addInputColumn(["Anamnesi.VITAMINA_D_TERAPIA_OSTEOPROTETTIVA", NULL, "vitamin D based osteoprotective therapy"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.TERAPIA_ALTRO_CHECKBOX,0))", "ForceCategorical", "other osteoprotective therapy"]);
  // bmi
  $db_fit->addInputColumn(["0+IF(ISNULL(Anamnesi.BMI) OR Anamnesi.BMI = -1, NULL, Anamnesi.BMI)", NULL, "body mass index"]);
  // fragility fractures in spine (one or more)
  // checkbox+value("Anamnesi.FRATTURA_VERTEBRE_CHECKBOX" "Anamnesi.FRATTURA_VERTEBRE")
  // $db_fit->addInputColumn(["CONCAT('', IF(Anamnesi.FRATTURA_VERTEBRE_CHECKBOX, Anamnesi.FRATTURA_VERTEBRE, '0'))", "ForceCategorical", "Anamnesi.N_FRATTURE_VERTEBRE"]);
  $db_fit->addInputColumn(["CONCAT('', IF(ISNULL(Anamnesi.FRATTURA_VERTEBRE), '0', Anamnesi.FRATTURA_VERTEBRE))", "ForceCategorical", "spinal fractures"]);
  // fragility fractures in hip (one or more)
  $db_fit->addInputColumn(["CONCAT('', IF(ISNULL(Anamnesi.FRATTURA_FEMORE), '0', Anamnesi.FRATTURA_FEMORE))", "ForceCategorical", "femoral fractures"]);
  // fragility fractures in other sites (one or more)
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.FRATTURA_SITI_DIVERSI,0))", "ForceCategorical", "fractures in other sites"]);
  // familiarity
  $db_fit->addInputColumn(["Anamnesi.FRATTURA_FAMILIARITA", NULL, "fracture familiarity"]);
  // current smoker
  // checkbox+value("Anamnesi.ABUSO_FUMO_CHECKBOX" "Anamnesi.ABUSO_FUMO")
  $db_fit->addInputColumn(["CONCAT('', IF(ISNULL(Anamnesi.ABUSO_FUMO_CHECKBOX),'No',IF(Anamnesi.ABUSO_FUMO_CHECKBOX, Anamnesi.ABUSO_FUMO, 'No')))", "ForceCategorical", "current smoker"]);
  // alcol abuse
  // checkbox+value("Anamnesi.ALCOL_CHECKBOX" "Anamnesi.ALCOL")
  $db_fit->addInputColumn(["CONCAT('', IF(ISNULL(Anamnesi.ALCOL_CHECKBOX),NULL,IF(Anamnesi.ALCOL_CHECKBOX, Anamnesi.ALCOL, 'No')))", "ForceCategorical", "alcohol use"]);
  // current corticosteoroid use
  // checkbox+value("Anamnesi.USO_CORTISONE_CHECKBOX" "Anamnesi.USO_CORTISONE")
  $db_fit->addInputColumn(["CONCAT('', IF(ISNULL(Anamnesi.USO_CORTISONE_CHECKBOX),'No',IF(Anamnesi.USO_CORTISONE_CHECKBOX, Anamnesi.USO_CORTISONE, 'No')))", "ForceCategorical", "cortisone"]);
  // current illnesses
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.MALATTIE_ATTUALI_ARTRITE_REUM,0))", "ForceCategorical", "rheumatoid arthritis"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.MALATTIE_ATTUALI_ARTRITE_PSOR,0))", "ForceCategorical", "psoriatic arthritis"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.MALATTIE_ATTUALI_LUPUS,0))", "ForceCategorical", "lupus"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.MALATTIE_ATTUALI_SCLERODERMIA,0))", "ForceCategorical", "scleroderma"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.MALATTIE_ATTUALI_ALTRE_CONNETTIVITI,0))", "ForceCategorical", "other connective tissue diseases"]);
  // secondary causes
  $db_fit->addInputColumn(["Anamnesi.CAUSE_OSTEOPOROSI_SECONDARIA", ["ForceCategoricalBinary", function ($input) {
      $input = trim($input);
      if ($input == "NULL") {
        $values = NULL;
      } else {
        $values = preg_split("/[\n\r]+/", $input);
      }
      // var_dump($values);
      return $values;
    }
  ]]);
  $db_fit->addInputColumn(["CONCAT('', IF(Anamnesi.CAUSE_OSTEOPOROSI_SECONDARIA = 'NULL',0,IF(ISNULL(Anamnesi.CAUSE_OSTEOPOROSI_SECONDARIA),0,1)))", "ForceCategorical", "Secondary causes"]);
  // clinical information (20 fields)
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.PATOLOGIE_UTERINE_CHECKBOX,0))", "ForceCategorical", "endometriosis"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.NEOPLASIA_CHECKBOX,0))", "ForceCategorical", "neoplasia"]);
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
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.PATOLOGIA_EPATICA,0))", "ForceCategorical", "hepatic pathologies"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.PAROLOGIA_ESOFAGEA,0))", "ForceCategorical", "esophageal pathologies"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.GASTRO_DUODENITE,0))", "ForceCategorical", "gastroduodenitis"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.GASTRO_RESEZIONE,0))", "ForceCategorical", "gastrectomy"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.RESEZIONE_INTESTINALE,0))", "ForceCategorical", "bowel resection"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.MICI,0))", "ForceCategorical", "inflammatory bowel disease"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Anamnesi.ALTRE_PATOLOGIE_CHECKBOX,0))", "ForceCategorical", "other diseases"]);
  $db_fit->addInputColumn(["0+COALESCE(Anamnesi.VITAMINA_D,0)", NULL, "vitamin D-25OH"]);
  // previous DXA spine total Z score
  $db_fit->addInputColumn(["Anamnesi.COLONNA_Z_SCORE", NULL, "previous spine Z-score"]);
  // previous DXA spine total T score
  $db_fit->addInputColumn(["Anamnesi.COLONNA_T_SCORE", NULL, "previous spine T-score"]);
  // previous DXA hip total Z score
  $db_fit->addInputColumn(["Anamnesi.FEMORE_Z_SCORE", NULL, "previous femur Z-score"]);
  // previous DXA hip total T score
  $db_fit->addInputColumn(["Anamnesi.FEMORE_T_SCORE", NULL, "previous femur Z-score"]);
  // spine (normal, osteopenic, osteoporotic)
  // checkbox+value("Diagnosi.SITUAZIONE_COLONNA_CHECKBOX" "Diagnosi.SITUAZIONE_COLONNA")
  // $db_fit->addInputColumn(["CONCAT('', IF(Diagnosi.SITUAZIONE_COLONNA_CHECKBOX, Diagnosi.SITUAZIONE_COLONNA, 'Normale'))", "ForceCategorical", "Diagnosi.N_SITUAZIONE_COLONNA"]);
  $db_fit->addInputColumn(["Diagnosi.SITUAZIONE_COLONNA", "ForceCategorical", "spine status"]);
  // hip (normal, osteopenic, osteoporotic)
  // merge(checkbox+value(Diagnosi.SITUAZIONE_FEMORE_SN...),checkbox+value(SITUAZIONE_FEMORE_DX...))
  $db_fit->addInputColumn(["CONCAT('', IF(Diagnosi.SITUAZIONE_FEMORE_SN_CHECKBOX, Diagnosi.SITUAZIONE_FEMORE_SN, IF(Diagnosi.SITUAZIONE_FEMORE_DX_CHECKBOX, Diagnosi.SITUAZIONE_FEMORE_DX, NULL)))", "ForceCategorical", "femur status"]);

  // $db_fit->addInputColumn("Diagnosi.OSTEOPOROSI_GRAVE");

  $db_fit->addInputColumn(["CONCAT('', IF(!ISNULL(Diagnosi.COLONNA_NON_ANALIZZABILE) AND !ISNULL(Diagnosi.VERTEBRE_NON_ANALIZZATE_CHECKBOX),IF(Diagnosi.COLONNA_NON_ANALIZZABILE,'No',IF(Diagnosi.VERTEBRE_NON_ANALIZZATE_CHECKBOX,'Parzialmente','Tutta')),NULL))", "ForceCategorical", "portion of analyzed spine"]);
  $db_fit->addInputColumn(["Diagnosi.COLONNA_VALORI_SUPERIORI", NULL, "spine with too high density values"]);
  $db_fit->addInputColumn(["CONCAT('', IF(ISNULL(Diagnosi.FEMORE_NON_ANALIZZABILE),NULL,IF(Diagnosi.FEMORE_NON_ANALIZZABILE,'0','1')))", "ForceCategorical", "femur is analyzed"]);
  
  // FRAX
  // nullif(Diagnosi.FRAX_APPLICABILE, checkbox+value("Diagnosi.FRAX_PERCENTUALE" "Diagnosi.FRAX_FRATTURE_MAGGIORI" true))
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Diagnosi.FRAX_APPLICABILE,0))", "ForceCategorical", "FRAX is applicable"]);
  $db_fit->addInputColumn(["IF(Diagnosi.FRAX_APPLICABILE,0+IF(ISNULL(Diagnosi.FRAX_PERCENTUALE),NULL,IF(Diagnosi.FRAX_PERCENTUALE OR Diagnosi.FRAX_FRATTURE_MAGGIORI < 0.1, 0, Diagnosi.FRAX_FRATTURE_MAGGIORI)),NULL)", NULL, "FRAX (major fractures)"]);
  // nullif(Diagnosi.FRAX_APPLICABILE, checkbox+value("Diagnosi.FRAX_COLLO_FEMORE_PERCENTUALE" "Diagnosi.FRAX_COLLO_FEMORE" true))
  $db_fit->addInputColumn(["IF(Diagnosi.FRAX_APPLICABILE,0+IF(ISNULL(Diagnosi.FRAX_COLLO_FEMORE_PERCENTUALE),NULL,IF(Diagnosi.FRAX_COLLO_FEMORE_PERCENTUALE OR Diagnosi.FRAX_COLLO_FEMORE < 0.1, 0, Diagnosi.FRAX_COLLO_FEMORE)),NULL)", NULL, "FRAX (femur)"]);

  // DeFRA
  // map(["Diagnosi.DEFRA" => true, "Diagnosi.DEFRA_PERCENTUALE_01" => 0, "Diagnosi.DEFRA_PERCENTUALE_50" => 50])
  // TODO this query should work, but these field can be misused, so it's a good idea to recheck every now and then.
  $db_fit->addInputColumn(["CONCAT('', COALESCE(Diagnosi.DEFRA_APPLICABILE,0))", "ForceCategorical", "DeFRA is applicable"]);
  $db_fit->addInputColumn(["IF(Diagnosi.DEFRA_APPLICABILE,0+IF(ISNULL(Diagnosi.DEFRA_PERCENTUALE_01),NULL,IF((Diagnosi.DEFRA_PERCENTUALE_01 OR Diagnosi.DEFRA < 0.1) AND Diagnosi.DEFRA_PERCENTUALE_50 = 0, 0,IF(ISNULL(Diagnosi.DEFRA_PERCENTUALE_50),NULL,IF(Diagnosi.DEFRA_PERCENTUALE_50 OR Diagnosi.DEFRA > 50, 50, Diagnosi.DEFRA)))),NULL)", NULL, "DeFRA"]);

  // FRAX_AGGIUSTATO
  // $db_fit->addInputColumn(["IF(Diagnosi.FRAX_AGGIUSTATO_APPLICABILE,0+IF(Diagnosi.FRAX_AGGIUSTATO_PERCENTUALE, 0, FRAX_FRATTURE_MAGGIORI_AGGIUSTATO_VALORE),NULL)", NULL, "Diagnosi.ALG_FRAX_AGG_FRATTURE"]);
  // $db_fit->addInputColumn(["IF(Diagnosi.FRAX_AGGIUSTATO_APPLICABILE,0+IF(Diagnosi.FRAX_COLLO_FEMORE_AGGIUSTATO_PERCENTUALE, 0, FRAX_COLLO_FEMORE_AGGIUSTATO_VALORE),NULL)", NULL, "Diagnosi.ALG_FRAX_AGG_FEMORE"]);

  // TBS
  // $db_fit->addInputColumn(["IF(Diagnosi.TBS_COLONNA_APPLICABILE,0+IF(Diagnosi.TBS_COLONNA_PERCENTUALE, 0, TBS_COLONNA_VALORE),NULL)", NULL, "Diagnosi.ALG_TBS"]);

  // $db_fit->addInputColumn("Densitometrie.SPINE_CHECKBOX");
  // $db_fit->addInputColumn("Densitometrie.HIP_R_CHECKBOX");
  // $db_fit->addInputColumn("Densitometrie.HIP_L_CHECKBOX");

  // current DXA spine total Z score
  $db_fit->addInputColumn(["Densitometrie.TOT_Z_SCORE", NULL, "spine Z-score"]);
  // current DXA spine total T score
  $db_fit->addInputColumn(["Densitometrie.TOT_T_SCORE", NULL, "spine T-score"]);
  // current DXA hip total Z score
  $db_fit->addInputColumn(["Densitometrie.NECK_Z_SCORE", NULL, "femur Z-score"]);
  // current DXA hip total T score
  $db_fit->addInputColumn(["Densitometrie.NECK_T_SCORE", NULL, "femur T-score"]);

  $db_fit->setOutputColumns([
    ["RaccomandazioniTerapeuticheUnitarie.TIPO",
      [
        ["RaccomandazioniTerapeuticheUnitarie", ["RaccomandazioniTerapeuticheUnitarie.ID_RACCOMANDAZIONE_TERAPEUTICA = RaccomandazioniTerapeutiche.ID"
        , "RaccomandazioniTerapeuticheUnitarie.TIPO != 'Indagini approfondimento'"], "LEFT JOIN"]
        // , "RaccomandazioniTerapeuticheUnitarie.TIPO = 'Vitamina D Supplementazione'"], "LEFT JOIN"]
      ],
      "ForceCategoricalBinary"],
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

  $start = microtime(TRUE);
  $db_fit->updateModel();
  $end = microtime(TRUE);
  echo "updateModel took " . ($end - $start) . " seconds to complete." . PHP_EOL;
  
  echo "AVAILABLE MODELS:" . PHP_EOL;
  var_dump($db_fit->listAvailableModels());

  $db_fit->predictByIdentifier(1);

  // $db_fit->test_all_capabilities();
  // $db_fit->predictByIdentifier(15);
  // $db_fit->predictByIdentifier(1);
  // $db_fit->predictByIdentifier(2);
  // $db_fit->predictByIdentifier(9);
  // $db_fit->predictByIdentifier(3);
}

function testMed2() {
  $db = getDBConnection();

  $db_fit = new DBFit($db);
  $db_fit->setTrainingMode([.8, .2]);

  $db_fit->setInputTables([
    "Anamnesi",
    ["Referti", "Anamnesi.ID_REFERTO = Referti.ID"], 
    ["Diagnosi", "Diagnosi.ID_REFERTO = Referti.ID"], 
    ["RaccomandazioniTerapeutiche", "RaccomandazioniTerapeutiche.ID_REFERTO = Referti.ID"], 
    ["RaccomandazioniTerapeuticheUnitarie", "RaccomandazioniTerapeuticheUnitarie.ID_RACCOMANDAZIONE_TERAPEUTICA = RaccomandazioniTerapeutiche.ID"], 
    ["ElementiTerapici", "ElementiTerapici.ID_RACCOMANDAZIONE_TERAPEUTICA_UNITARIA = RaccomandazioniTerapeuticheUnitarie.ID"],
    ["PrincipiAttivi", "ElementiTerapici.ID_PRINCIPIO_ATTIVO = PrincipiAttivi.ID"], 
    ["Pazienti", "Pazienti.ID = Referti.ID_PAZIENTE"]
  ]);
  $db_fit->setIdentifierColumnName("RaccomandazioniTerapeuticheUnitarie.ID");
  $db_fit->setDefaultOption("textTreatment", ["BinaryBagOfWords", 10]);
  $db_fit->setInputColumns("*");
  $db_fit->setInputColumns([
    "RaccomandazioniTerapeutiche.DATA_ANNULLA",
    "RaccomandazioniTerapeutiche.MOTIVO_ANNULLA",
    "RaccomandazioniTerapeutiche.STATO",
    "RaccomandazioniTerapeutiche.DATA_SALVA",
    "RaccomandazioniTerapeutiche.ALTRO",
    "RaccomandazioniTerapeutiche.ALTRO_CHECKBOX",
    "RaccomandazioniTerapeutiche.SOSPENSIONE_TERAPIA_CHECKBOX",
    "RaccomandazioniTerapeutiche.SOSPENSIONE_TERAPIA_FARMACO",
    "RaccomandazioniTerapeutiche.SOSPENSIONE_TERAPIA_MESI",
    "Referti.DATA_ANNULLA",
    "Referti.MOTIVO_ANNULLA",
    "Referti.STATO",
  ]);
  // $db_fit->setLimit(10);
  // $db_fit->setLimit(100);
  $db_fit->setOutputColumnName("RaccomandazioniTerapeuticheUnitarie.TIPO"
  // , "ForceCategoricalBinary"
  );
  $lr = new PRip();
  // $lr->setNumOptimizations(10);
  $lr->setNumOptimizations(3);
  $db_fit->setLearner($lr);
  $db_fit->test_all_capabilities();
  $db_fit->predictByIdentifier(10);
  $db_fit->predictByIdentifier(15);
  $db_fit->predictByIdentifier(1);
  $db_fit->predictByIdentifier(2);
  $db_fit->predictByIdentifier(3);
}


function testMed() {
  $db = getDBConnection();

  $db_fit = new DBFit($db);
  $db_fit->setTrainingMode([.8, .2]);

  /*
  select * from Anamnesi
  inner join Diagnosi on Anamnesi.ID_REFERTO = Diagnosi.ID_REFERTO
    and Anamnesi.DATA_SALVA = Diagnosi.DATA_SALVA
  inner join Referti on Anamnesi.ID_REFERTO = Referti.ID
  inner join Pazienti on Anamnesi.ID = Referti.ID_PAZIENTE
  //  inner join Spine on Pazienti.PATIENT_KEY = Spine.PATIENT_KEY
  //    and Anamnesi.DATA_SALVA = Spine.DATA_SALVA
  //  inner join ScanAnalysis on Pazienti.PATIENT_KEY = ScanAnalysis.PATIENT_KEY
  //    and Anamnesi.DATA_SALVA = ScanAnalysis.DATA_SALVA
    
   */
  $db_fit->setInputTables([
    "Anamnesi",
    ["Referti", "Anamnesi.ID_REFERTO = Referti.ID"], 
    ["Diagnosi", "Diagnosi.ID_REFERTO = Referti.ID"], 
    ["RaccomandazioniTerapeutiche", "RaccomandazioniTerapeutiche.ID_REFERTO = Referti.ID"], 
    ["RaccomandazioniTerapeuticheUnitarie", "RaccomandazioniTerapeuticheUnitarie.ID_RACCOMANDAZIONE_TERAPEUTICA = RaccomandazioniTerapeutiche.ID"], 
    ["Pazienti", "Pazienti.ID = Referti.ID_PAZIENTE"]
  ]);
  $db_fit->setIdentifierColumnName("Referti.ID");
  $db_fit->setDefaultOption("textTreatment", ["BinaryBagOfWords", 10]);
  $db_fit->setInputColumns([
"Anamnesi.DATA_SALVA",
"Anamnesi.DATA_ANNULLA",
"Anamnesi.MOTIVO_ANNULLA",
"Anamnesi.INVIATA_DA",
"Anamnesi.INVIATA_DA_GINECOLOGO",
"Anamnesi.INVIATA_DA_ALTRO_SPECIALISTA",
"Anamnesi.STATO_MENOPAUSALE",
"Anamnesi.ULTIMA_MESTRUAZIONE",
"Anamnesi.ETA_MENOPAUSA",
"Anamnesi.TERAPIA_STATO",
"Anamnesi.TERAPIA_ANNI_SOSPENSIONE",
"Anamnesi.TERAPIA_OSTEOPROTETTIVA_ORMONALE",
"Anamnesi.TERAPIA_OSTEOPROTETTIVA_ORMONALE_LISTA",
"Anamnesi.TERAPIA_OSTEOPROTETTIVA_SPECIFICA",
"Anamnesi.TERAPIA_OSTEOPROTETTIVA_SPECIFICA_LISTA",
"Anamnesi.VITAMINA_D_TERAPIA_OSTEOPROTETTIVA",
"Anamnesi.VITAMINA_D_TERAPIA_OSTEOPROTETTIVA_LISTA",
"Anamnesi.TERAPIA_ALTRO_CHECKBOX",
"Anamnesi.TERAPIA_ALTRO",
"Anamnesi.TERAPIA_COMPLIANCE",
"Anamnesi.PESO",
"Anamnesi.ALTEZZA",
"Anamnesi.BMI",
"Anamnesi.FRATTURA_VERTEBRE_CHECKBOX",
"Anamnesi.FRATTURA_VERTEBRE",
"Anamnesi.FRATTURA_FEMORE",
"Anamnesi.FRATTURA_SITI_DIVERSI",
"Anamnesi.FRATTURA_SITI_DIVERSI_ALTRO",
"Anamnesi.FRATTURA_FAMILIARITA",
"Anamnesi.ABUSO_FUMO_CHECKBOX",
"Anamnesi.ABUSO_FUMO",
"Anamnesi.USO_CORTISONE_CHECKBOX",
"Anamnesi.USO_CORTISONE",
"Anamnesi.MALATTIE_ATTUALI_CHECKBOX",
"Anamnesi.MALATTIE_ATTUALI_ARTRITE_REUM",
"Anamnesi.MALATTIE_ATTUALI_ARTRITE_PSOR",
"Anamnesi.MALATTIE_ATTUALI_LUPUS",
"Anamnesi.MALATTIE_ATTUALI_SCLERODERMIA",
"Anamnesi.MALATTIE_ATTUALI_ALTRE_CONNETTIVITI",
"Anamnesi.CAUSE_OSTEOPOROSI_SECONDARIA_CHECKBOX",
"Anamnesi.CAUSE_OSTEOPOROSI_SECONDARIA",
"Anamnesi.ALCOL_CHECKBOX",
"Anamnesi.ALCOL",
"Anamnesi.PATOLOGIE_UTERINE_CHECKBOX",
"Anamnesi.PATOLOGIE_UTERINE_DIAGNOSI",
"Anamnesi.NEOPLASIA_CHECKBOX",
"Anamnesi.NEOPLASIA_MAMMARIA_DATA",
"Anamnesi.NEOPLASIA_MAMMARIA_TERAPIA",
"Anamnesi.SINTOMI_VASOMOTORI",
"Anamnesi.SINTOMI_DISTROFICI",
"Anamnesi.DISLIPIDEMIA_CHECKBOX",
"Anamnesi.DISLIPIDEMIA_TERAPIA",
"Anamnesi.IPERTENSIONE",
"Anamnesi.RISCHIO_TEV",
"Anamnesi.PATOLOGIA_CARDIACA",
"Anamnesi.PATOLOGIA_VASCOLARE",
"Anamnesi.INSUFFICIENZA_RENALE",
"Anamnesi.PATOLOGIA_RESPIRATORIA",
"Anamnesi.PATOLOGIA_CAVO_ORALE_CHECKBOX",
"Anamnesi.PATOLOGIA_CAVO_ORALE",
"Anamnesi.PATOLOGIA_EPATICA",
"Anamnesi.PAROLOGIA_ESOFAGEA",
"Anamnesi.GASTRO_DUODENITE",
"Anamnesi.GASTRO_RESEZIONE",
"Anamnesi.RESEZIONE_INTESTINALE",
"Anamnesi.MICI",
"Anamnesi.VITAMINA_D_CHECKBOX",
"Anamnesi.VITAMINA_D",
"Anamnesi.ALTRE_PATOLOGIE_CHECKBOX",
"Anamnesi.ALTRE_PATOLOGIE",
"Anamnesi.ALLERGIE_CHECKBOX",
"Anamnesi.ALLERGIE",
"Anamnesi.INTOLLERANZE_CHECKBOX",
"Anamnesi.INTOLLERANZE",
"Anamnesi.DENSITOMETRIA_PRECEDENTE_CHECKBOX",
"Anamnesi.DENSITOMETRIA_PRECEDENTE_DATA",
"Anamnesi.DENSITOMETRIA_PRECEDENTE_INTERNA",
"Anamnesi.MORFOMETRIA_PRECEDENTE_CHECKBOX",
"Anamnesi.MORFOMETRIA_PRECEDENTE_DATA",
"Anamnesi.MORFOMETRIA_PRECEDENTE_INTERNA",
"Anamnesi.BODY_SCAN_PRECEDENTE_CHECKBOX",
"Anamnesi.BODY_SCAN_PRECEDENTE_DATA",
"Anamnesi.BODY_SCAN_PRECEDENTE_INTERNA",
"Anamnesi.VERTEBRE_VALUTATE_L1",
"Anamnesi.VERTEBRE_VALUTATE_L2",
"Anamnesi.VERTEBRE_VALUTATE_L3",
"Anamnesi.VERTEBRE_VALUTATE_L4",
"Anamnesi.COLONNA_APPLICABILE",
"Anamnesi.COLONNA_T_SCORE",
"Anamnesi.COLONNA_Z_SCORE",
"Anamnesi.FEMORE_LATO",
"Anamnesi.FEMORE_APPLICABILE",
"Anamnesi.FEMORE_T_SCORE",
"Anamnesi.FEMORE_Z_SCORE",
"Diagnosi.STATO",
"Diagnosi.DATA_SALVA",
"Diagnosi.DATA_ANNULLA",
"Diagnosi.MOTIVO_ANNULLA",
"Diagnosi.SITUAZIONE_COLONNA_CHECKBOX",
"Diagnosi.SITUAZIONE_COLONNA",
"Diagnosi.SITUAZIONE_FEMORE_SN_CHECKBOX",
"Diagnosi.SITUAZIONE_FEMORE_SN",
"Diagnosi.SITUAZIONE_FEMORE_DX_CHECKBOX",
"Diagnosi.SITUAZIONE_FEMORE_DX",
"Diagnosi.OSTEOPOROSI_GRAVE",
"Diagnosi.VERTEBRE_NON_ANALIZZATE_CHECKBOX",
"Diagnosi.VERTEBRE_NON_ANALIZZATE_L1",
"Diagnosi.VERTEBRE_NON_ANALIZZATE_L2",
"Diagnosi.VERTEBRE_NON_ANALIZZATE_L3",
"Diagnosi.VERTEBRE_NON_ANALIZZATE_L4",
"Diagnosi.COLONNA_NON_ANALIZZABILE",
"Diagnosi.COLONNA_VALORI_SUPERIORI",
"Diagnosi.FEMORE_NON_ANALIZZABILE",
"Diagnosi.FRAX_APPLICABILE",
"Diagnosi.FRAX_PERCENTUALE",
"Diagnosi.FRAX_FRATTURE_MAGGIORI",
"Diagnosi.FRAX_COLLO_FEMORE_PERCENTUALE",
"Diagnosi.FRAX_COLLO_FEMORE",
"Diagnosi.DEFRA_APPLICABILE",
"Diagnosi.DEFRA_PERCENTUALE_01",
"Diagnosi.DEFRA_PERCENTUALE_50",
"Diagnosi.DEFRA",
"Diagnosi.FRAX_AGGIUSTATO_APPLICABILE",
"Diagnosi.FRAX_AGGIUSTATO_PERCENTUALE",
"Diagnosi.FRAX_FRATTURE_MAGGIORI_AGGIUSTATO_VALORE",
"Diagnosi.FRAX_COLLO_FEMORE_AGGIUSTATO_PERCENTUALE",
"Diagnosi.FRAX_COLLO_FEMORE_AGGIUSTATO_VALORE",
"Diagnosi.TBS_COLONNA_APPLICABILE",
"Diagnosi.TBS_COLONNA_PERCENTUALE",
"Diagnosi.TBS_COLONNA_VALORE",
"Diagnosi.VALUTAZIONE_INTEGRATA",
"Pazienti.PATIENT_KEY",
"Pazienti.DATA_NASCITA",
"Pazienti.SESSO",
"Pazienti.ETNIA",
"Pazienti.MEDICO_RIFERIMENTO",
"Pazienti.COMMENTO",
"RaccomandazioniTerapeutiche.DATA_ANNULLA",
"RaccomandazioniTerapeutiche.MOTIVO_ANNULLA",
"RaccomandazioniTerapeutiche.STATO",
"RaccomandazioniTerapeutiche.DATA_SALVA",
"RaccomandazioniTerapeutiche.ALTRO",
"RaccomandazioniTerapeutiche.ALTRO_CHECKBOX",
"RaccomandazioniTerapeutiche.SOSPENSIONE_TERAPIA_CHECKBOX",
"RaccomandazioniTerapeutiche.SOSPENSIONE_TERAPIA_FARMACO",
"RaccomandazioniTerapeutiche.SOSPENSIONE_TERAPIA_MESI",
"Referti.DATA_ANNULLA",
"Referti.MOTIVO_ANNULLA",
"Referti.STATO",
]);
  $db_fit->setLimit(10);
  // $db_fit->setLimit(100);
  // $db_fit->setLimit(1000);
  $db_fit->setWhereClauses([]);
  $db_fit->setOutputColumnName("RaccomandazioniTerapeuticheUnitarie.TIPO"
     ,"ForceCategoricalBinary"
  );
  $lr = new PRip();
  // $lr->setNumOptimizations(10);
  $lr->setNumOptimizations(3);
  $db_fit->setLearner($lr);
  $db_fit->test_all_capabilities();
  $db_fit->predictByIdentifier(10);
  $db_fit->predictByIdentifier(15);
  $db_fit->predictByIdentifier(1);
  $db_fit->predictByIdentifier(2);
  $db_fit->predictByIdentifier(3);
}

function testSPAM() {
  $db = getDBConnection();
  $model_type = "RuleBased";
  $learning_method = "PRip";

  $table_names = "spam";
  $columns = [["Category", "ForceCategorical"], ["Message", ["BinaryBagOfWords", 10]]];
  $output_column_name = "Category";

  $db_fit = new DBFit($db);
  $db_fit->setIdentifierColumnName("ID");
  $db_fit->setTrainingMode([.8, .2]);
  $db_fit->setInputTables($table_names);
  $db_fit->setInputColumns($columns);
  $db_fit->setOutputColumnName($output_column_name);
  // $db_fit->setModelType($model_type);
  $db_fit->setLearningMethod($learning_method);
  $db_fit->test_all_capabilities();
  $db_fit->predictByIdentifier(1);
}

function testSilly() {
  $db = getDBConnection();
  $model_type = "RuleBased";
  $learning_method = "PRip";

  $table_names = ["patients"];
  $columns = ["ID", "Gender", ["BirthDate", "YearsSince", "Age"], "Sillyness"];
  $whereClauses = NULL;
  $output_column_name = "Sillyness";

  $db_fit = new DBFit($db);
  $db_fit->setTrainingMode("FullTraining");
  $db_fit->setInputTables($table_names);
  $db_fit->setInputColumns($columns);
  $db_fit->setWhereClauses($whereClauses);
  $db_fit->setOutputColumnName($output_column_name);
  // $db_fit->setModelType($model_type);
  $db_fit->setLearningMethod($learning_method);
  $db_fit->test_all_capabilities();
}

function testWinery() {
  $db = getDBConnection();
  $model_type = "RuleBased";
  $learning_method = "PRip";

  $table_names = ["winery"];
  $columns = [
    ["winery.country", "ForceCategorical"],
    ["winery.description", ["BinaryBagOfWords", 14]],
  ];
  $whereClauses = [];
  $output_column_name = "winery.country";

  $db_fit = new DBFit($db);
  // $db_fit->setTrainingMode("FullTraining");
  $db_fit->setTrainingMode([.8, .2]);
  $db_fit->setInputTables($table_names);
  $db_fit->setInputColumns($columns);
  $db_fit->setWhereClauses($whereClauses);
  $db_fit->setLimit(100);
  $db_fit->setOutputColumnName($output_column_name);
  // $db_fit->setModelType($model_type);
  $db_fit->setLearningMethod($learning_method);
  $db_fit->test_all_capabilities();
  $db_fit->loadModel("models/2020-07-20_22:25:14");
  $db_fit->test(NULL);
}

function testSillyWithJoin() {
  
  $db = getDBConnection();
  $model_type = "RuleBased";
  $learning_method = "PRip";

  $table_names = ["patients", "reports"];
  $columns = [
    "patients.Gender",
    "patients.ID",
    ["patients.BirthDate", "MonthsSince", "MonthAge"],
    "patients.Sillyness",
    "reports.Date",
    ["reports.PatientState", NULL, "State"],
    ["reports.PatientHeartbeatMeasure", NULL, "Heartbeat"],
    ["reports.PatientID", NULL, "ID"],
    ["reports.DoctorIsFrares"]
  ];
  $whereClauses = ["patients.ID = reports.PatientID"];
  $output_column_name = "patients.Sillyness";

  $db_fit = new DBFit($db);
  $db_fit->setTrainingMode([.8, .2]);
  $db_fit->setInputTables($table_names);
  $db_fit->setInputColumns($columns);
  $db_fit->setWhereClauses($whereClauses);
  $db_fit->setOutputColumnName($output_column_name);
  // $db_fit->setModelType($model_type);
  $db_fit->setLearningMethod($learning_method);
  $db_fit->test_all_capabilities();

}

function testCovid() {

  $db = getDBConnection();
  $model_type = "RuleBased";
  $learning_method = "PRip";

  $table_names = ["covid19_italy_province"];
  $columns = [["Date", "DaysSince", "DaysAgo"] , ["ProvinceCode", "ForceCategorical"], "Date", "TotalPositiveCases"];
  $whereClauses = NULL;
  $output_column_name = "ProvinceCode";

  $db_fit = new DBFit($db);
  // $db_fit->setModelType($model_type);
  $db_fit->setInputTables($table_names);
  $db_fit->setInputColumns($columns);
  $db_fit->setWhereClauses($whereClauses);
  $db_fit->setOutputColumnName($output_column_name);
  $db_fit->setLimit(1000);
  $db_fit->setLearningMethod($learning_method);
  $db_fit->test_all_capabilities();

}

function testDiabetes() {
  $trainingMode = [.8, .2];
  //$trainingMode = "FullTraining";
  $data = Instances::createFromARFF("diabetes.arff");
  /* training modes */
  switch (true) {
    /* Full training: use data for both training and testing */
    case $trainingMode == "FullTraining":
      $trainData = $data;
      $testData = $data;
      break;
    
    /* Train+test split */
    case is_array($trainingMode):
      $trRat = $trainingMode[0]/($trainingMode[0]+$trainingMode[1]);
      list($trainData, $testData) = Instances::partition($data, $trRat);
      
      break;
    
    default:
      die_error("Unknown training mode ('$trainingMode')");
      break;
  }

  /* Train */
  $model = new RuleBasedModel();
  $learner = new PRip();
  $model->fit($trainData, $learner);

  echo "Ultimately, here are the extracted rules: " . PHP_EOL;
  foreach ($model->getRules() as $x => $rule) {
    echo $x . ": " . $rule->toString() . PHP_EOL;
  }

  /* Test */
  $ground_truths = [];
  $classAttr = $testData->getClassAttribute();

  for ($x = 0; $x < $testData->numInstances(); $x++) {
    $ground_truths[] = $classAttr->reprVal($testData->inst_classValue($x));
  }

  // $testData->dropOutputAttr();
  $predictions = $model->predict($testData);

  // echo "\$ground_truths : " . get_var_dump($ground_truths) . PHP_EOL;
  // echo "\$predictions : " . get_var_dump($predictions) . PHP_EOL;
  $negatives = 0;
  $positives = 0;
  foreach ($ground_truths as $val) {
    echo str_pad($val, 10, " ");
  }
  echo "\n";
  foreach ($predictions as $val) {
    echo str_pad($val, 10, " ");
  }
  echo "\n";
  foreach ($ground_truths as $i => $val) {
    if ($ground_truths[$i] != $predictions[$i]) {
      $negatives++;
    } else {
      $positives++;
    }
  }
  echo "Test accuracy: " . ($positives/($positives+$negatives));
  echo "\n";

}
?>