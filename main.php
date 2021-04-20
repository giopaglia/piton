<?php
chdir(dirname(__FILE__));
ini_set('xdebug.halt_level', E_WARNING|E_NOTICE|E_USER_WARNING|E_USER_NOTICE);
ini_set('memory_limit', '1024M'); // or you could use 1G
ini_set('max_execution_time', 3000);
set_time_limit(3000);

include "lib.php";
include "local-lib.php";

include "DBFit.php";
include_once "DiscriminativeModel/WittgensteinLearner.php";
include_once "DiscriminativeModel/SklearnLearner.php";

/******************************************************************************
*                                                                             *
*                              Here I test stuff                              *
*                                                                             *
*******************************************************************************/
// $numOptimizations = 2;
// $numFolds = 5;
// $minNo = 2;
// {
    // echo "PRip" . PHP_EOL;
//   echo "PARAMETERS: ($numOptimizations, $numFolds, $minNo)" . PHP_EOL;
//   echo "numOptimizations: $numOptimizations" . PHP_EOL;
//   echo "numFolds: $numFolds" . PHP_EOL;
//   echo "minNo: $minNo" . PHP_EOL;

//   $lr = new PRip(NULL);
//   $lr->setNumOptimizations($numOptimizations);
//   $lr->setNumFolds($numFolds);
//   $lr->setMinNo($minNo);
//   testMed($lr);
// }

{
  echo "WittgensteinLearner" . PHP_EOL;
  $ouputDB = getPitonDBConnection();
  $lr = new WittgensteinLearner("RIPPERk", $ouputDB);
  testMed($lr);
}

/******************************************************************************/
/******************************************************************************/
/******************************************************************************/
/******************************************************************************/

function testMed($lr) {
  $inputDB = getInputDBConnection();
  $ouputDB = getPitonDBConnection();

  $db_fit = new DBFit($inputDB, $ouputDB);

  $db_fit->setTrainingMode([.8, .2]);
  $db_fit->setCutOffValue(0.10);
  $db_fit->setLearner($lr);
  $db_fit->setDefaultOption("textLanguage", "it");
  
  $db_fit->setInputTables([
    "referti"
  , ["pazienti", "pazienti.id = referti.id_paziente", "LEFT JOIN"]
  , ["anamnesi", "anamnesi.id_referto = referti.id", "LEFT JOIN"]
  , ["diagnosi", "diagnosi.id_referto = referti.id", "LEFT JOIN"]
  , ["densitometrie", "densitometrie.id_referto = referti.id", "LEFT JOIN"]
  // id_referto is still in raccomandazioni_terapeutiche even if the whole table is changed, maybe they have been incorporated
  //, ["raccomandazioni_terapeutiche", ["raccomandazioni_terapeutiche.id_referto = referti.id"], "LEFT JOIN"]
  ]);
  
  $db_fit->setWhereClauses([
    [
      "referti.data_referto BETWEEN '2018-09-01' AND '2020-08-31'"
    , "pazienti.sesso = 'F'"
    , "!ISNULL(anamnesi.stato_menopausale)"
    , "DATEDIFF(referti.data_referto,pazienti.data_nascita) / 365 >= 40"
    // END structural constraints
    
    // END begin constraints for manual cleaning
    , "anamnesi.bmi is NOT NULL"
    , "anamnesi.bmi != -1"
    // raccomandazioni_terapeutiche is the new raccomandazioni_terapeutiche_unitarie, while raccomandazioni_terapeutiche_unitarie is the new elementi_terapici
    , ["referti.id", "NOT IN", ["reuse_current_query", 1, ["!ISNULL(raccomandazioni_terapeutiche.tipo)", "ISNULL(principi_attivi.nome)"]]]
    , ["referti.id", "NOT IN", ["reuse_current_query", 1, [], ["GROUP BY" => ["raccomandazioni_terapeutiche.tipo", "principi_attivi.nome", "referti.id"], "HAVING" => "COUNT(*) > 1"]]]
    ],
  ]);

  $db_fit->setOrderByClauses([["referti.data_referto", "ASC"]]);
  
  $db_fit->setIdentifierColumnName("referti.id");
 
  // age
  $db_fit->addInputColumn(["DATEDIFF(referti.data_referto,pazienti.data_nascita) / 365", NULL, "age"]);

  // bmi
  $db_fit->addInputColumn(["0+IF(ISNULL(anamnesi.bmi) OR anamnesi.bmi = -1, NULL, anamnesi.bmi)", NULL, "body mass index"]);
  
  // gender
  $db_fit->addInputColumn(["pazienti.sesso", "ForceCategorical", "gender"]);
  // menopause state (if relevant)
  $db_fit->addInputColumn(["anamnesi.stato_menopausale", NULL, "menopause state"]);
  // age at last menopause (if relevant)
  $db_fit->addInputColumn(["anamnesi.eta_menopausa", NULL, "age at last menopause"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.terapia_stato,'Mai'))", "ForceCategorical", "therapy status"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(IF(anamnesi.terapia_compliance,anamnesi.terapia_osteoprotettiva_ormonale,'0'),'0'))", "ForceCategorical", "hormonal osteoprotective therapy"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(IF(anamnesi.terapia_compliance,anamnesi.terapia_osteoprotettiva_specifica,'0'),'0'))", "ForceCategorical", "specific osteoprotective therapy"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(IF(anamnesi.terapia_compliance,anamnesi.vitamina_d_terapia_osteoprotettiva,'0'),'0'))", "ForceCategorical", "vitamin D based osteoprotective therapy"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(IF(anamnesi.terapia_compliance,anamnesi.terapia_altro_checkbox,'0'),'0'))", "ForceCategorical", "other osteoprotective therapy"]);
  // fragility fractures in spine (one or more)
  // checkbox+value("Anamnesi.FRATTURA_VERTEBRE_CHECKBOX" "Anamnesi.FRATTURA_VERTEBRE")
  $spinal_fractures = "CONCAT('', IF(ISNULL(anamnesi.frattura_vertebre), '0', anamnesi.frattura_vertebre))";
  $db_fit->addInputColumn([$spinal_fractures, "ForceCategorical", "vertebral fractures"]);
  // fragility fractures in hip (one or more)
  $femoral_fractures = "CONCAT('', IF(ISNULL(anamnesi.frattura_femore), '0', anamnesi.frattura_femore))";
  $db_fit->addInputColumn([$femoral_fractures, "ForceCategorical", "femoral fractures"]);
  // fragility fractures in other sites (one or more)
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.frattura_siti_diversi,0))", "ForceCategorical", "fractures in other sites"]);
  // familiarity
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.frattura_familiarita,0))", "ForceCategorical", "fracture familiarity"]);
  // current smoker
  // checkbox+value("Anamnesi.ABUSO_FUMO_CHECKBOX" "Anamnesi.ABUSO_FUMO")
  $db_fit->addInputColumn(["CONCAT('', IF(ISNULL(anamnesi.abuso_fumo),'No',IF(anamnesi.abuso_fumo, anamnesi.abuso_fumo, 'No')))", "ForceCategorical", "smoking habits"]);
  // alcol abuse
  // checkbox+value("Anamnesi.ALCOL_CHECKBOX" "Anamnesi.ALCOL")
  $db_fit->addInputColumn(["CONCAT('', IF(ISNULL(anamnesi.alcol),NULL,IF(anamnesi.alcol, anamnesi.alcol, 'No')))", "ForceCategorical", "alcohol intake"]);
  // current corticosteoroid use
  // checkbox+value("Anamnesi.USO_CORTISONE_CHECKBOX" "Anamnesi.USO_CORTISONE")
  $db_fit->addInputColumn(["CONCAT('', IF(ISNULL(anamnesi.uso_cortisone),'No',IF(anamnesi.uso_cortisone, anamnesi.uso_cortisone, 'No')))", "ForceCategorical", "cortisone"]);
  // current illnesses
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.malattie_attuali_artrite_reum,0))", "ForceCategorical", "rheumatoid arthritis"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.malattie_attuali_artrite_psor,0))", "ForceCategorical", "psoriatic arthritis"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.malattie_attuali_lupus,0))", "ForceCategorical", "systemic lupus"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.malattie_attuali_sclerodermia,0))", "ForceCategorical", "scleroderma"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.malattie_attuali_altre_connettiviti,0))", "ForceCategorical", "other connective tissue diseases"]);

  // secondary causes
  // note: now there exists a column for each of them in the CMO database
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.diabete_insulino_dipendente,0))", "ForceCategorical", "diabetes mellitus"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.menopausa_prematura,0))", "ForceCategorical", "early menopause"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.malnutrizione_cronica,0))", "ForceCategorical", "chronic malnutrition"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.osteogenesi_imperfecta_in_eta_adulta,0))", "ForceCategorical", "adult osteogenesis imperfecta"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.ipertiroidismo_non_trattato_per_lungo_tempo,0))", "ForceCategorical", "untreated chronic hyperthyroidism"]);

  // FRAX
  // defra/frax_applicabile => algoritmi non applicabile contrario!
  // $db_fit->addInputColumn(["IF(diagnosi.FRAX_APPLICABILE,0+IF(ISNULL(Diagnosi.FRAX_PERCENTUALE),NULL,IF(Diagnosi.FRAX_PERCENTUALE OR Diagnosi.FRAX_FRATTURE_MAGGIORI < 0.1, 0, Diagnosi.FRAX_FRATTURE_MAGGIORI)),NULL)", NULL, "FRAX (major fractures)"]);
  $db_fit->addInputColumn(["IF(ISNULL(diagnosi.algoritmi_non_applicabile),0+IF(ISNULL(diagnosi.frax_fratture_maggiori_percentuale_01),NULL,IF(diagnosi.frax_fratture_maggiori_percentuale_01 OR diagnosi.frax_fratture_maggiori < 0.1, 0, diagnosi.frax_fratture_maggiori)),NULL)", NULL, "FRAX (major fractures)"]);
  // $db_fit->addInputColumn(["IF(Diagnosi.FRAX_APPLICABILE,0+IF(ISNULL(Diagnosi.FRAX_COLLO_FEMORE_PERCENTUALE),NULL,IF(Diagnosi.FRAX_COLLO_FEMORE_PERCENTUALE OR Diagnosi.FRAX_COLLO_FEMORE < 0.1, 0, Diagnosi.FRAX_COLLO_FEMORE)),NULL)", NULL, "FRAX (femur)"]);

  // DeFRA
  $db_fit->addInputColumn(["IF(ISNULL(diagnosi.algoritmi_non_applicabile),0+IF(ISNULL(diagnosi.defra_percentuale_01),NULL,IF((diagnosi.defra_percentuale_01 OR diagnosi.defra < 0.1) AND diagnosi.defra_percentuale_50 = 0, 0,IF(ISNULL(diagnosi.defra_percentuale_50),NULL,IF(diagnosi.defra_percentuale_50 OR diagnosi.defra > 50, 50, diagnosi.defra)))),NULL)", NULL, "DeFRA"]);

  // clinical information (20 fields)
  // TODO solve checkboxes migration
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.patologie_uterine,0))", "ForceCategorical", "endometrial pathologies"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.neoplasia,0))", "ForceCategorical", "breast cancer"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.sintomi_vasomotori,0))", "ForceCategorical", "vasomotor symptoms"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.sintomi_distrofici,0))", "ForceCategorical", "distrofic symptoms"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.dislipidemia,0))", "ForceCategorical", "dyslipidemia"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.ipertensione,0))", "ForceCategorical", "hypertension"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.rischio_tev,0))", "ForceCategorical", "venous thromboembolism risk factors"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.patologia_cardiaca,0))", "ForceCategorical", "cardiac pathologies"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.patologia_vascolare,0))", "ForceCategorical", "vascular pathologies"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.insufficienza_renale,0))", "ForceCategorical", "kidney failure"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.patologia_respiratoria,0))", "ForceCategorical", "respiratory pathologies"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.patologia_cavo_orale,0))", "ForceCategorical", "oral pathologies"]);
  // parologia or patologia?
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.parologia_esofagea,0))", "ForceCategorical", "esophageal pathologies"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.gastro_duodenite,0))", "ForceCategorical", "gastroduodenitis"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.gastro_resezione,0))", "ForceCategorical", "gastrectomy"]);
  $db_fit->addInputColumn(["CONCAT('', COALESCE(anamnesi.resezione_intestinale,0))", "ForceCategorical", "bowel resection"]);
  $db_fit->addInputColumn(["anamnesi.vitamina_d", NULL, "vitamin D-25OH"]);
  // previous DXA spine total T score
  $db_fit->addInputColumn(["anamnesi.colonna_t_score", NULL, "previous spine T-score"]);
  // previous DXA spine total Z score
  $db_fit->addInputColumn(["anamnesi.colonna_z_score", NULL, "previous spine Z-score"]);
  // previous DXA hip total T score
  $db_fit->addInputColumn(["anamnesi.femore_t_score", NULL, "previous neck T-score"]);
  // previous DXA hip total Z score
  $db_fit->addInputColumn(["anamnesi.femore_z_score", NULL, "previous neck Z-score"]);

  $db_fit->addInputColumn(["CONCAT('', IF($spinal_fractures != '0' OR $femoral_fractures != '0','1',diagnosi.osteoporosi_grave))", "ForceCategorical", "severe osteoporosis"]);

  $db_fit->addInputColumn(["CONCAT('', IF(diagnosi.situazione_femore_sn_checkbox, diagnosi.situazione_femore_sn, IF(diagnosi.situazione_femore_dx_checkbox, diagnosi.situazione_femore_dx, NULL)))", "ForceCategorical", "femur status"]);

  // spine (normal, osteopenic, osteoporotic)
  // checkbox+value("Diagnosi.SITUAZIONE_COLONNA_CHECKBOX" "Diagnosi.SITUAZIONE_COLONNA")
  $db_fit->addInputColumn(["diagnosi.situazione_colonna", "ForceCategorical", "spine status"]);

  // current DXA spine total T score
  $db_fit->addInputColumn(["densitometrie.tot_t_score", NULL, "spine T-score"]);
  // current DXA spine total Z score
  $db_fit->addInputColumn(["densitometrie.tot_z_score", NULL, "spine Z-score"]);
  // I didn't know how to migrate the following
  // current DXA hip total T score
  // now we have separated right and left
  // QUESTION: maybe here we want to consider them as the same attribute? Is it relevant they are separated? TODO
  // $db_fit->addInputColumn(["densitometrie.NECK_T_SCORE", NULL, "neck T-score"]);
  $db_fit->addInputColumn(["densitometrie.neck_l_t_score", NULL, "neck left T-score"]);
  $db_fit->addInputColumn(["densitometrie.neck_r_t_score", NULL, "neck right T-score"]);
  // current DXA hip total Z score
  // $db_fit->addInputColumn(["densitometrie.NECK_Z_SCORE", NULL, "neck Z-score"]);
  $db_fit->addInputColumn(["densitometrie.neck_l_z_score", NULL, "neck left Z-score"]);
  $db_fit->addInputColumn(["densitometrie.neck_r_z_score", NULL, "neck right Z-score"]);

  $db_fit->setOutputColumns([
    // raccomandazioni_terapeutiche is the new raccomandazioni_terapeutiche_unitarie?
    ["raccomandazioni_terapeutiche.tipo",
      [
        /*["raccomandazioni_terapeutiche", ["raccomandazioni_terapeutiche.id_raccomandazione_terapeutica = raccomandazioni_terapeutiche.id"
        , "raccomandazioni_terapeutiche.tipo != 'Indagini approfondimento'"], "LEFT JOIN"]*/
        /* ["raccomandazioni_terapeutiche_unitarie", ["raccomandazioni_terapeutiche.id = raccomandazioni_terapeutiche_unitarie.id_raccomandazione_terapeutica"
        , "raccomandazioni_terapeutiche.tipo != 'Indagini approfondimento'"], "LEFT JOIN"] */
        ["raccomandazioni_terapeutiche", ["raccomandazioni_terapeutiche.id_referto = referti.id",
        "raccomandazioni_terapeutiche.tipo != 'Indagini approfondimento'"], "LEFT JOIN"]
      ],
      "ForceCategoricalBinary",
      "Terapia"
    ],
    // ElementiTerapici? Is it the new raccomandazioni_terapeutiche_unitarie?
    [
      "principi_attivi.nome",
      [
        ["raccomandazioni_terapeutiche_unitarie", ["raccomandazioni_terapeutiche.id = raccomandazioni_terapeutiche_unitarie.id_raccomandazione_terapeutica"], "LEFT JOIN"],
        ["principi_attivi", "raccomandazioni_terapeutiche_unitarie.id_principio_attivo = principi_attivi.id", "LEFT JOIN"]
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
  $db_fit->updateModel(); // here i have a problem, DEBUG
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
