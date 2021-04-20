
testMed2();
exit();
testMed1();
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


function testMed1() {
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

 foreach ($testData->iterateInsts() as $instance_id => $inst) {
    $ground_truths[$instance_id] = $classAttr->reprVal($testData->inst_classValue($instance_id));
  }

  // $testData->dropOutputAttr();
  $predictions = $model->predict($testData)["predictions"];

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
  foreach ($ground_truths as $instance_id => $val) {
    if ($ground_truths[$instance_id] != $predictions[$instance_id]) {
      $negatives++;
    } else {
      $positives++;
    }
  }
  echo "Test accuracy: " . ($positives/($positives+$negatives));
  echo "\n";

}
