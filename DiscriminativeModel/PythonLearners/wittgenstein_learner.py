#!/usr/bin/env python

import sys
import numpy as np
import pandas as pd
import wittgenstein as lw

import local_lib
import lib

# ------------------------------------------------------------------
# -- USING WITTGENSTEIN'S RIPPER TO TRAIN CMO MODELS ---------------
# ------------------------------------------------------------------
conn = local_lib.getDBConnection()  # connection to the database

# TRAINING

# Data preprocess is done by the php package, so data is already partitioned
train = pd.read_sql_table("trainData", conn)

# First, we must check if the class attribute is binary
class_attr = lib.get_class_attr(train)
if not lib.is_binary(train[class_attr]):
    print("Error: class attribute is not binary")
    quit()

train = train.drop(['__ID_piton__'], axis='columns')    # Drops the ID column (I don't need it)
train = lib.clean_dataframe(train, 0.8) # Removes the attributes with more than 90% NaN values,
                                        # then removes the lines with numeric NaN values

# Use fit method to train a RIPPER or IREP classifier:
ripper_clf = lw.RIPPER()
ripper_clf.fit(train, class_feat=class_attr, pos_class=class_attr) # Checks that the class attribute is binary
ripper_clf

# Access the underlting model with the ruleset_ attribute, or output it with out_model().
# A ruleset is a disjunction of conjunctions-- 'V' representes 'or'; '^' representes 'and'.
# In other words, the model predicts positive class if any of the inner-nested condition-combinations are all true:
ripper_clf.out_model()

#'(esophageal pathologies == \'0\') and (DeFRA >= 17) and (DeFRA <= 41)'
