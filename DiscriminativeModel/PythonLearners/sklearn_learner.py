#!/usr/bin/env python

# ----- Python modules used ------------------------------------------------------------------------------------------------------
import sys
from sklearn.datasets import load_iris
from sklearn import tree
import pandas as pd
# ----- Personal modules used ----------------------------------------------------------------------------------------------------
import local_lib
import lib
# ----- Arguments parsing --------------------------------------------------------------------------------------------------------
classifier                = sys.argv[1]   # The classifier algorithm (CART) to be used
tableName                 = sys.argv[2]   # Name of the temporary table in the database used to communicate the dataframe
criterion                 = sys.argv[3]   # The function to measure the quality of a split (gini by default, or entropy) 
splitter                  = sys.argv[4]   # The strategy used to choose the split at each node (best by default, or random)
max_depth                 = sys.argv[5]   # The maximum depth of a tree
min_samples_split         = sys.argv[6]   # The minimum number of samples required to split an internal node
min_samples_leaf          = sys.argv[7]   # The minimum number of samples required to be at a leaf node
min_weight_fraction_leaf  = sys.argv[8]   # The minimum weighted fraction of the sum total of weights (of all the input samples)
                                          # required to be at a leaf node
max_features              = sys.argv[9]   # The number of features to consider when looking for the best split
random_state              = sys.argv[10]  # Controls the randomness of the estimator
max_leaf_nodes            = sys.argv[11]  # Grow a tree with ``max_leaf_nodes`` in best-first fashion
min_impurity_decrease     = sys.argv[12]  # A node will be split if this split induces a decrease of the impurity greater than or
                                          # equal to this value
min_impurity_split        = sys.argv[13]  # Threshold for early stopping in tree growth
class_weight              = sys.argv[14]  # Weights associated with classes in the form ``{class_label: weight}``
ccp_alpha                 = sys.argv[15]  #Complexity parameter used for Minimal Cost-Complexity Pruning
# ----- Setting default values if argument is None -------------------------------------------------------------------------------
if criterion.strip() == 'None':
    criterion = "gini"
if splitter.strip() == 'None':
    splitter = "best"
if max_depth.strip() == 'None':
    max_depth = None
else:
    max_depth = int(max_depth)
if min_samples_split.strip() == 'None':
    min_samples_split = 2
else:
    min_samples_split = float(min_samples_split)
if min_samples_leaf.strip() == 'None':
    min_samples_leaf = 1
else:
    min_samples_leaf = float(min_samples_leaf)
if min_weight_fraction_leaf.strip() == 'None':
    min_weight_fraction_leaf = 0.0
else:
    min_weight_fraction_leaf = float(min_weight_fraction_leaf)
if max_features.strip() == 'None':
    max_features = None
if random_state.strip() == 'None':
    random_state = None
else:
    random_state = int(random_state)
if max_leaf_nodes.strip() == 'None':
    max_leaf_nodes = None
else:
    max_leaf_nodes = int(max_leaf_nodes)
if min_impurity_decrease.strip() == 'None':
    min_impurity_decrease = 0.0
else:
    min_impurity_decrease = float(min_impurity_decrease)
if min_impurity_split.strip() == 'None':
    min_impurity_split = 0.0
else:
    min_impurity_split = float(min_impurity_split)
if class_weight.strip() == 'None':
    class_weight = None
else:
    class_weight = dict(class_weight)
if ccp_alpha.strip() == 'None':
    ccp_alpha = 0.0
else:
    ccp_alpha = float(ccp_alpha)
# --------------------------------------------------------------------------------------------------------------------------------
# ----- USING SCIKIT-LEARN'S LEARNERS TO TRAIN MODELS ----------------------------------------------------------------------------
# --------------------------------------------------------------------------------------------------------------------------------
conn = local_lib.getDBConnection()          # Connection to the database
train = pd.read_sql_table(tableName, conn)  # Reads the training data frame from the a database table
                                            # Data preprocess is done by the php package, so data is already partitioned

class_attr = lib.get_class_attr(train)              # Gets the class attribute
                                                    # For now, it appears it doesn't have to be binary in this case

train = train.drop(['__ID_piton__'], axis='columns')    # Drops the ID column (I don't need it)
train = lib.clean_dataframe(train, 0.1)                 # Removes the attributes with more than 10% NaN values,
                                                        # then removes the lines with numeric NaN values

X_train = train.drop(class_attr, axis=1)
y_train = train[class_attr]

# First dummify your categorical features and booleanize your class values to make sklearn happy
X_train = pd.get_dummies(X_train, columns=X_train.select_dtypes('object').columns)
y_train = y_train.map(lambda x: 1 if x==class_attr else 0)

# Storing the name of the features
fn = []
i = 0
for col in X_train.columns:
  fn.append(col)
  i+=1

if classifier == "CART":  # Classification using the CART algorithm
  clf = tree.DecisionTreeClassifier(criterion=criterion, splitter=splitter, max_depth=max_depth, min_samples_split=min_samples_split,
                                    min_samples_leaf=min_samples_leaf, min_weight_fraction_leaf=min_weight_fraction_leaf,
                                    max_features=max_features, random_state=random_state, max_leaf_nodes=max_leaf_nodes,
                                    min_impurity_decrease=min_impurity_decrease, min_impurity_split=min_impurity_split,
                                    class_weight=class_weight, ccp_alpha=ccp_alpha)
  clf = clf.fit(X_train, y_train)

  # DEBUG: printinting the resulting tree
  # r = tree.export_text(clf, feature_names=fn) # where class: 1, class_attr, where class: 0, NO_class_attr # debug
  # print(r) # debug

  neg_class_attr = lib.get_negative_class_value(train)
  print("extracted_rule_based_model: [\n")
  lib.tree_to_ruleset(clf, fn, [class_attr, neg_class_attr])
  print("\n]")
else:
    print("Error: the specified classifier is invalid. Please choose between RIPPERk and IREP.")
    sys.exit()