<?php
/*
 * Interface for learner/optimizers
 */
abstract class Learner {
    /* Returns an uninitialized DiscriminativeModel */
    abstract function initModel() : DiscriminativeModel;
  
    /* Trains a DiscriminativeModel */
    abstract function teach(DiscriminativeModel &$model, Instances $data);
}
?>