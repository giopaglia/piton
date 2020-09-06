<?php

chdir(dirname(__FILE__));
ini_set('xdebug.halt_level', E_WARNING|E_NOTICE|E_USER_WARNING|E_USER_NOTICE);
ini_set('memory_limit', '1024M'); // or you could use 1G
ini_set('max_execution_time', 3000);
set_time_limit(3000);

include "lib.php";
include "local-lib.php";

include "DBFit.php";

/*
TODOs:
- Text processing via NlpTools
- Parallelize code ( https://medium.com/@rossbulat/true-php7-multi-threading-how-to-rebuild-php-and-use-pthreads-bed4243c0561 )
- Implement an unweighted version of Instances
- Fix those == that should actually be === https://stackoverflow.com/questions/12151997/why-does-1234-1234-test-evaluate-to-true#comment16259587_12151997
- Add method setSQL() that directly asks for the SELECT - FROM - WHERE query;
- Make sql querying secure with addslashes or whatever
 */

/****************************************************
*                                                   *
*                 Here I test stuff                 *
*                                                   *
****************************************************/

testMed3();
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



function testMed3() {
  $db = getDBConnection();

  $db_fit = new DBFit($db);
  $db_fit->setTrainingMode([.8, .2]);

  $db_fit->setInputTables([
    "Referti"
  , ["Pazienti", "Pazienti.ID = Referti.ID_PAZIENTE", "LEFT JOIN"]
  , ["Anamnesi", "Anamnesi.ID_REFERTO = Referti.ID", "LEFT JOIN"]
  , ["Diagnosi", "Diagnosi.ID_REFERTO = Referti.ID", "LEFT JOIN"]
  , ["Densitometrie", "Densitometrie.ID_REFERTO = Referti.ID", "LEFT JOIN"]
  , ["RaccomandazioniTerapeutiche", ["RaccomandazioniTerapeutiche.ID_REFERTO = Referti.ID"], "LEFT JOIN"]
  ]);

  // $db_fit->setLimit(20);
  // $db_fit->setLimit(100);
  // $db_fit->setLimit(500);
  
  $db_fit->setWhereClauses(
    [
      "Pazienti.SESSO = 'F'",
      "Referti.DATA_REFERTO BETWEEN '2018-07-18' AND '2020-08-31'"
    ]
  );

  $lr = new PRip();
  $lr->setNumOptimizations(3);
  // $lr->setNumOptimizations(10); TODO
  $db_fit->setLearner($lr);

  // $db_fit->setDefaultOption("textLanguage", "it");
  // $db_fit->setDefaultOption("textTreatment", ["BinaryBagOfWords", 10]);
  
  $db_fit->setIdentifierColumnName("Referti.ID");
  // gender
  $db_fit->addInputColumn(["Pazienti.SESSO", "ForceCategorical"]);
  // menopause state (if relevant)
  $db_fit->addInputColumn(["Pazienti.DATA_NASCITA", "YearsSince", "Age"]);
  $db_fit->addInputColumn("Anamnesi.STATO_MENOPAUSALE");
  // age at last menopause (if relevant)
  $db_fit->addInputColumn("Anamnesi.ETA_MENOPAUSA");
  $db_fit->addInputColumn("Anamnesi.TERAPIA_STATO");
  $db_fit->addInputColumn("Anamnesi.TERAPIA_ANNI_SOSPENSIONE");
  $db_fit->addInputColumn("Anamnesi.TERAPIA_OSTEOPROTETTIVA_ORMONALE");
  $db_fit->addInputColumn(["Anamnesi.TERAPIA_OSTEOPROTETTIVA_SPECIFICA", "ForceCategorical"]);
  $db_fit->addInputColumn("Anamnesi.VITAMINA_D_TERAPIA_OSTEOPROTETTIVA");
  $db_fit->addInputColumn("Anamnesi.TERAPIA_ALTRO_CHECKBOX");
  // bmi
  $db_fit->addInputColumn("Anamnesi.BMI");
  // fragility fractures in spine (one or more)
  // checkbox+value("Anamnesi.FRATTURA_VERTEBRE_CHECKBOX" "Anamnesi.FRATTURA_VERTEBRE")
  $db_fit->addInputColumn(["CONCAT('', IF(Anamnesi.FRATTURA_VERTEBRE_CHECKBOX, Anamnesi.FRATTURA_VERTEBRE, 'No'))", "ForceCategorical", "Anamnesi.N_FRATTURE_VERTEBRE"]);
  // fragility fractures in hip (one or more)
  $db_fit->addInputColumn(["CONCAT('', IF(ISNULL(Anamnesi.FRATTURA_FEMORE), 0, Anamnesi.FRATTURA_FEMORE))", "ForceCategorical", "Anamnesi.N_FRATTURE_FEMORE"]);
  // fragility fractures in other sites (one or more)
  $db_fit->addInputColumn("Anamnesi.FRATTURA_SITI_DIVERSI");
  // familiarity
  $db_fit->addInputColumn("Anamnesi.FRATTURA_FAMILIARITA");
  // current smoker
  // checkbox+value("Anamnesi.ABUSO_FUMO_CHECKBOX" "Anamnesi.ABUSO_FUMO")
  $db_fit->addInputColumn(["CONCAT('', IF(Anamnesi.ABUSO_FUMO_CHECKBOX, Anamnesi.ABUSO_FUMO, 'No'))", "ForceCategorical", "Anamnesi.N_ABUSO_FUMO"]);
  // current corticosteoroid use
  // checkbox+value("Anamnesi.USO_CORTISONE_CHECKBOX" "Anamnesi.USO_CORTISONE")
  $db_fit->addInputColumn(["CONCAT('', IF(Anamnesi.USO_CORTISONE_CHECKBOX, Anamnesi.USO_CORTISONE, 'No'))", "ForceCategorical", "Anamnesi.N_USO_CORTISONE"]);
  // current illnesses
  $db_fit->addInputColumn("Anamnesi.MALATTIE_ATTUALI_ARTRITE_REUM");
  $db_fit->addInputColumn("Anamnesi.MALATTIE_ATTUALI_ARTRITE_PSOR");
  $db_fit->addInputColumn("Anamnesi.MALATTIE_ATTUALI_LUPUS");
  $db_fit->addInputColumn("Anamnesi.MALATTIE_ATTUALI_SCLERODERMIA");
  $db_fit->addInputColumn("Anamnesi.MALATTIE_ATTUALI_ALTRE_CONNETTIVITI");
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
  // alcol abuse
  // checkbox+value("Anamnesi.ALCOL_CHECKBOX" "Anamnesi.ALCOL")
  $db_fit->addInputColumn(["CONCAT('', IF(Anamnesi.ALCOL_CHECKBOX, Anamnesi.ALCOL, 'No'))", "ForceCategorical", "Anamnesi.N_ALCOL"]);
  // clinical information (20 fields)PATOLOGIE_UTERINE_CHECKBOX
  $db_fit->addInputColumn("Anamnesi.NEOPLASIA_CHECKBOX");
  $db_fit->addInputColumn("Anamnesi.SINTOMI_VASOMOTORI");
  $db_fit->addInputColumn("Anamnesi.SINTOMI_DISTROFICI");
  $db_fit->addInputColumn("Anamnesi.DISLIPIDEMIA_CHECKBOX");
  $db_fit->addInputColumn("Anamnesi.IPERTENSIONE");
  $db_fit->addInputColumn("Anamnesi.RISCHIO_TEV");
  $db_fit->addInputColumn("Anamnesi.PATOLOGIA_CARDIACA");
  $db_fit->addInputColumn("Anamnesi.PATOLOGIA_VASCOLARE");
  $db_fit->addInputColumn("Anamnesi.INSUFFICIENZA_RENALE");
  $db_fit->addInputColumn("Anamnesi.PATOLOGIA_RESPIRATORIA");
  $db_fit->addInputColumn("Anamnesi.PATOLOGIA_CAVO_ORALE_CHECKBOX");
  $db_fit->addInputColumn("Anamnesi.PATOLOGIA_EPATICA");
  $db_fit->addInputColumn("Anamnesi.PAROLOGIA_ESOFAGEA");
  $db_fit->addInputColumn("Anamnesi.GASTRO_DUODENITE");
  $db_fit->addInputColumn("Anamnesi.GASTRO_RESEZIONE");
  $db_fit->addInputColumn("Anamnesi.RESEZIONE_INTESTINALE");
  $db_fit->addInputColumn("Anamnesi.MICI");
  $db_fit->addInputColumn("Anamnesi.VITAMINA_D");
  $db_fit->addInputColumn("Anamnesi.ALTRE_PATOLOGIE_CHECKBOX");
  // previous DXA spine total T score
  $db_fit->addInputColumn("Anamnesi.COLONNA_T_SCORE");
  // previous DXA spine total Z score
  $db_fit->addInputColumn("Anamnesi.COLONNA_Z_SCORE");
  // previous DXA hip total T score
  $db_fit->addInputColumn("Anamnesi.FEMORE_T_SCORE");
  // previous DXA hip total Z score
  $db_fit->addInputColumn("Anamnesi.FEMORE_Z_SCORE");
  // spine (normal, osteopenic, osteoporotic)
  // checkbox+value("Diagnosi.SITUAZIONE_COLONNA_CHECKBOX" "Diagnosi.SITUAZIONE_COLONNA")
  $db_fit->addInputColumn(["CONCAT('', IF(Diagnosi.SITUAZIONE_COLONNA_CHECKBOX, Diagnosi.SITUAZIONE_COLONNA, 'Normale'))", "ForceCategorical", "Diagnosi.N_SITUAZIONE_COLONNA"]);
  // hip (normal, osteopenic, osteoporotic)
  // merge(checkbox+value(Diagnosi.SITUAZIONE_FEMORE_SN...),checkbox+value(SITUAZIONE_FEMORE_DX...))
  $db_fit->addInputColumn(["CONCAT('', IF(Diagnosi.SITUAZIONE_FEMORE_SN_CHECKBOX, Diagnosi.SITUAZIONE_FEMORE_SN, IF(Diagnosi.SITUAZIONE_FEMORE_DX_CHECKBOX, Diagnosi.SITUAZIONE_FEMORE_DX, 'Normale')))", "ForceCategorical", "Diagnosi.SITUAZIONE_FEMORE"]);
  
  $db_fit->addInputColumn("Diagnosi.OSTEOPOROSI_GRAVE");
  $db_fit->addInputColumn("Diagnosi.COLONNA_NON_ANALIZZABILE");
  $db_fit->addInputColumn("Diagnosi.COLONNA_VALORI_SUPERIORI");
  $db_fit->addInputColumn("Diagnosi.FEMORE_NON_ANALIZZABILE");
  
  // FRAX
  // nullif(Diagnosi.FRAX_APPLICABILE, checkbox+value("Diagnosi.FRAX_PERCENTUALE" "Diagnosi.FRAX_FRATTURE_MAGGIORI" true))
  $db_fit->addInputColumn(["IF(Diagnosi.FRAX_APPLICABILE,0+IF(Diagnosi.FRAX_PERCENTUALE, 0, Diagnosi.FRAX_FRATTURE_MAGGIORI),NULL)", NULL, "Diagnosi.ALG_FRAX_FRATTURE"]);
  // nullif(Diagnosi.FRAX_APPLICABILE, checkbox+value("Diagnosi.FRAX_COLLO_FEMORE_PERCENTUALE" "Diagnosi.FRAX_COLLO_FEMORE" true))
  $db_fit->addInputColumn(["IF(Diagnosi.FRAX_APPLICABILE,0+IF(Diagnosi.FRAX_COLLO_FEMORE_PERCENTUALE, 0, Diagnosi.FRAX_COLLO_FEMORE),NULL)", NULL, "Diagnosi.ALG_FRAX_FEMORE"]);

  // DeFRA
  // map(["Diagnosi.DEFRA" => true, "Diagnosi.DEFRA_PERCENTUALE_01" => 0, "Diagnosi.DEFRA_PERCENTUALE_50" => 50])
  $db_fit->addInputColumn(["IF(Diagnosi.DEFRA_APPLICABILE,0+IF(Diagnosi.DEFRA_PERCENTUALE_01, 0, IF(Diagnosi.DEFRA_PERCENTUALE_50, 50, Diagnosi.DEFRA)),NULL)", NULL, "Diagnosi.ALG_DEFRA"]);

  // FRAX_AGGIUSTATO
  $db_fit->addInputColumn(["IF(Diagnosi.FRAX_AGGIUSTATO_APPLICABILE,0+IF(Diagnosi.FRAX_AGGIUSTATO_PERCENTUALE, 0, FRAX_FRATTURE_MAGGIORI_AGGIUSTATO_VALORE),NULL)", NULL, "Diagnosi.ALG_FRAX_AGG_FRATTURE"]);
  $db_fit->addInputColumn(["IF(Diagnosi.FRAX_AGGIUSTATO_APPLICABILE,0+IF(Diagnosi.FRAX_COLLO_FEMORE_AGGIUSTATO_PERCENTUALE, 0, FRAX_COLLO_FEMORE_AGGIUSTATO_VALORE),NULL)", NULL, "Diagnosi.ALG_FRAX_AGG_FEMORE"]);

  // TBS
  $db_fit->addInputColumn(["IF(Diagnosi.TBS_COLONNA_APPLICABILE,0+IF(Diagnosi.TBS_COLONNA_PERCENTUALE, 0, TBS_COLONNA_VALORE),NULL)", NULL, "Diagnosi.ALG_FRAX_AGG_FEMORE"]);

  $db_fit->addInputColumn("Densitometrie.SPINE_CHECKBOX");
  $db_fit->addInputColumn("Densitometrie.HIP_R_CHECKBOX");
  $db_fit->addInputColumn("Densitometrie.HIP_L_CHECKBOX");

  // current DXA spine total T score
  $db_fit->addInputColumn("Densitometrie.TOT_T_SCORE");
  // current DXA spine total Z score
  $db_fit->addInputColumn("Densitometrie.TOT_Z_SCORE");
  // current DXA hip total T score
  $db_fit->addInputColumn("Densitometrie.NECK_T_SCORE");
  // current DXA hip total Z score
  $db_fit->addInputColumn("Densitometrie.NECK_Z_SCORE");

  $db_fit->setOutputColumns([
    ["RaccomandazioniTerapeuticheUnitarie.TIPO",
      [
        ["RaccomandazioniTerapeuticheUnitarie", ["RaccomandazioniTerapeuticheUnitarie.ID_RACCOMANDAZIONE_TERAPEUTICA = RaccomandazioniTerapeutiche.ID"
        , "RaccomandazioniTerapeuticheUnitarie.TIPO != 'Indagini approfondimento'"], "LEFT JOIN"]
      ],
      "ForceCategoricalBinary"],
    ["CONCAT(PrincipiAttivi.NOME, IF(!STRCMP(PrincipiAttivi.QUANTITA, 'NULL') || ISNULL(PrincipiAttivi.QUANTITA), '', CONCAT(' ', PrincipiAttivi.QUANTITA)))",
      [
        ["ElementiTerapici", ["ElementiTerapici.ID_RACCOMANDAZIONE_TERAPEUTICA_UNITARIA = RaccomandazioniTerapeuticheUnitarie.ID"], "LEFT JOIN"],
        ["PrincipiAttivi", "ElementiTerapici.ID_PRINCIPIO_ATTIVO = PrincipiAttivi.ID", "LEFT JOIN"]
      ],
      "ForceCategoricalBinary",
      "PrincipioAttivo"
    ]
  ]);

  $db_fit->test_all_capabilities();
  // $db_fit->predictByIdentifier(15);
  // $db_fit->predictByIdentifier(1);
  // $db_fit->predictByIdentifier(2);
  $db_fit->predictByIdentifier(9);
  $db_fit->predictByIdentifier(3);
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