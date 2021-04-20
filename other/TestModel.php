<?php

chdir(dirname(__FILE__));
ini_set('xdebug.halt_level', E_WARNING|E_NOTICE|E_USER_WARNING|E_USER_NOTICE);
ini_set('memory_limit', '1024M'); // or you could use 1G
ini_set('max_execution_time', 3000);
set_time_limit(3000);

include "lib.php";
include "local-lib.php";

include "DBFit.php";

echo "<pre>";
// ClassificationRule::fromString(" => [2]");
// // ClassificationRule::fromString(" => [-1]");
// ClassificationRule::fromString("()   => [0]");
// ClassificationRule::fromString("(Ciao = 2) => [1]");
// ClassificationRule::fromString("(Ciao >= 2)  => [1]");
// // ClassificationRule::fromString("(Ciao != 2)  => [1]");
// // ClassificationRule::fromString("(Ciao == 2 and Ciao2 <= 1)    => [1]");
// // ClassificationRule::fromString("(Ciao == 2     and    Ciao2 <= 1)    => [1]");
// ClassificationRule::fromString("(Ciao = 2 and Ciao2 <= 1)    => [1]");
// ClassificationRule::fromString("(Ciao = 2     and    Ciao2 <= 1)    => [1]");

// $a = RuleBasedModel::fromString("(Ciao = 2     and    Ciao2 <= 1)    => [1]
// (Ciao2 = 2     and    Ciao6 <= 1)    => [1]
// (Ciao3 = 2     and    Ciao <= 1)    => [1]
// (Ciao5 = 2     and    Ciao2 <= 1)    => [0]
// (Ciao5 = 2     and    Ciao2 <= 1    and    Ciao2 <= 1)    => [4]
// (Ciao5 = 2)    => [0]
//   => [3]");

$data = Instances::createFromARFF("query_processato_femmine_Tscore_colonna_bilanciato.arff");
// echo $data->toString() . PHP_EOL;

// $a = RuleBasedModel::fromString("
// (BMI <= 25.9993) and (ETÀ SCAN >= 56.279452) and (BMI <= 23.4082) and (BMI <= 20.4914) => TOT T_SCORE COLONNA=osteoporosi
// (BMI <= 25.0709) and (ETÀ SCAN >= 57.572603) and (ETÀ SCAN >= 70.221918) and (TERAPIA STATO = Mai) => TOT T_SCORE COLONNA=osteoporosi
// (ETÀ SCAN >= 54.868493) and (BMI <= 27.5134) and (FRATTURA VERTEBRE = più di 1) and (ETÀ SCAN <= 63.772603) => TOT T_SCORE COLONNA=osteoporosi
//  => TOT T_SCORE COLONNA=normale
// new DiscreteAttribute("TOT T_SCORE COLONNA", "output enum", ["osteoporosi", "osteopenia", "normale"]));

$a = RuleBasedModel::fromString("

(FRATTURA VERTEBRE = 1) AND (ABUSO FUMO = 0) AND (FRATTURA FEMORE = 0) AND (FRATTURA FAMILIARITA’ = 0) => osteoporosi
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene + mastectomia) AND (NEOPLASIA MAMMARIA ETÀ <= 52) => osteoporosi
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = quadrantectomia + tamoxifene) AND (FRATTURA VERTEBRE = 0) AND (STATO MENOPAUSALE = Postmenopausa spontanea) AND (IPERTENSIONE = 0) => osteoporosi
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) AND (NEOPLASIA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene + mastectomia) => normale
(NEOPLASIA MAMMARIA TERAPIA = radioterapia + mastectomia + chemioterapia + letrozolo) => osteoporosi
(NEOPLASIA MAMMARIA TERAPIA = radioterapia + chemioterapia + anastrozolo) => osteopenia
(NEOPLASIA MAMMARIA TERAPIA = quadrantectomia + radioterapia + tamoxifene + chemioterapia) => osteopenia
(NEOPLASIA MAMMARIA TERAPIA = radioterapia + chemioterapia + aromatasi) => osteoporosi
(NEOPLASIA MAMMARIA TERAPIA = radioterapia + mastectomia) => osteoporosi
(NEOPLASIA MAMMARIA TERAPIA = quadrantectomia + tamoxifene + mastectomia + chemioterapia + recidiva) => osteopenia
(NEOPLASIA MAMMARIA TERAPIA = quadrantectomia + radioterapia + tamoxifene + mastectomia + anastrozolo + recidiva) => normale
(NEOPLASIA MAMMARIA TERAPIA = anastrozolo + sentinella) => osteopenia
(NEOPLASIA MAMMARIA TERAPIA = mastectomia + letrozolo + sentinella) => osteopenia
(NEOPLASIA MAMMARIA TERAPIA = quadrantectomia + radioterapia + chemioterapia + anastrozolo) => osteoporosi
(FRATTURA VERTEBRE = più di 1) AND (MALATTIE ATTUALI = artrite reum) AND (ABUSO FUMO = 0) AND (STATO MENOPAUSALE = Postmenopausa spontanea) AND (RISCHIO TEV = 0) => osteoporosi
(FRATTURA VERTEBRE = più di 1) AND (ABUSO FUMO = 0) AND (STATO MENOPAUSALE = Postmenopausa spontanea) AND (RISCHIO TEV = 0) AND (PATOLOGIA VASCOLARE = 0) AND (PATOLOGIA CARDIACA = 0) AND (IPERTENSIONE = 0) => osteoporosi
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene) => normale
(NEOPLASIA MAMMARIA TERAPIA = quadrantectomia + radioterapia + anastrozolo ) AND (FRATTURA VERTEBRE = 0) => normale
(NEOPLASIA MAMMARIA TERAPIA = quadrantectomia + radioterapia + tamoxifene) => osteopenia
(NEOPLASIA MAMMARIA TERAPIA = quadrantectomia) AND (FRATTURA SITI DIVERSI CHECKBOX = 0) => osteopenia
(NEOPLASIA MAMMARIA TERAPIA = quadrantectomia + radioterapia + anastrozolo ) => osteopenia
(NEOPLASIA MAMMARIA TERAPIA = radioterapia + tamoxifene + mastectomia + chemioterapia + recidiva) => normale
(NEOPLASIA MAMMARIA TERAPIA = quadrantectomia) => osteoporosi
(NEOPLASIA MAMMARIA TERAPIA = recidiva) => osteoporosi
(NEOPLASIA MAMMARIA TERAPIA = mastectomia + anastrozolo) => osteopenia
(NEOPLASIA MAMMARIA TERAPIA = radioterapia + mastectomia + chemioterapia + aromatasi) => osteopenia
(NEOPLASIA MAMMARIA TERAPIA = quadrantectomia + tamoxifene + mastectomia) => normale
(FRATTURA VERTEBRE = 1) AND (FRATTURA FEMORE = 0) AND (IPERTENSIONE = 0) => osteoporosi
(FRATTURA SITI DIVERSI CHECKBOX = 1) => osteoporosi
(NEOPLASIA MAMMARIA TERAPIA = tamoxifene + chemioterapia) => osteopenia
(FRATTURA VERTEBRE = più di 1) AND (ABUSO FUMO = 0) AND (STATO MENOPAUSALE = Postmenopausa spontanea) AND (PATOLOGIA ESOFAGEA = 0) => osteoporosi
(FRATTURA VERTEBRE = più di 1) AND (PATOLOGIA ESOFAGEA = 1) => osteopenia
(FRATTURA VERTEBRE = 0) AND (STATO MENOPAUSALE = Perimenopausa) => normale
(FRATTURA VERTEBRE = 0) AND (PATOLOGIE UTERINE DIAGNOSI = fibroma + isterectomia) => osteopenia
(FRATTURA VERTEBRE = 0) AND (PATOLOGIE UTERINE DIAGNOSI = fibroma +  istero-annessiectomia) => normale
(FRATTURA VERTEBRE = 0) AND (PATOLOGIE UTERINE DIAGNOSI = polipectomia + isteroscopia) => osteopenia
(FRATTURA VERTEBRE = 0) AND (PATOLOGIE UTERINE DIAGNOSI = ciste + annessiectomia) => osteoporosi
(FRATTURA VERTEBRE = 0) AND (PATOLOGIE UTERINE DIAGNOSI = istero-annessiectomia + endometriosi + carcinoma) => normale
(FRATTURA VERTEBRE = 0) AND (PATOLOGIE UTERINE DIAGNOSI = endometriosi) => osteopenia
(FRATTURA VERTEBRE = 0) AND (PATOLOGIE UTERINE DIAGNOSI = ciste) => osteopenia
(FRATTURA VERTEBRE = più di 1) => osteoporosi
(PATOLOGIE UTERINE DIAGNOSI = fibroma) AND (PATOLOGIA CARDIACA = 0) AND (TERAPIA OSTEOPROTETTIVA SPECIFICA CHECKBOX = 0) AND (PATOLOGIE UTERINE CHECKBOX = 0) => osteopenia
(PATOLOGIE UTERINE DIAGNOSI = fibroma) AND (VITAMINA D TERAPIA OSTEOPROTETTIVA CHECKBOX = 0) => normale
(STATO MENOPAUSALE = Postmenopausa spontanea) AND (FRATTURA FEMORE = 0) AND (TERAPIA OSTEOPROTETTIVA SPECIFICA CHECKBOX = 0) AND (NEOPLASIA CHECKBOX = 0) AND (IPERTENSIONE = 0) => osteopenia
() => osteoporosi
",
new DiscreteAttribute("TOT T_SCORE COLONNA", "output enum", ["osteoporosi", "osteopenia", "normale"]));
echo "MODEL:" . PHP_EOL . $a . PHP_EOL;

$data->appendPredictions($a);
echo $data->toString() . PHP_EOL;

echo get_var_dump($a->test($data)) . PHP_EOL;

echo "</pre>";

?>
