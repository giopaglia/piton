#!/usr/bin/env python

# ----- Python modules used ----------------------------------------------------------------------------------------
from sklearn.datasets import load_iris
from sklearn import tree
import pandas as pd
# ----- Personal modules used --------------------------------------------------------------------------------------
import local_lib
import lib

tableName = "reserved__tmpWittgensteinTrainData6061a6e0b95e3"  # DEBUG

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
#print(fn)

# It appears I don't have to encode the attributes names; in case, look at wittgenstein_learner

clf = tree.DecisionTreeClassifier()
clf = clf.fit(X_train, y_train)
#r = tree.export_text(clf, feature_names=fn) # where class: 1, class_attr, where class: 0, NO_class_attr # debug
#print(r) # debug
neg_class_attr = lib.get_negative_class_value(train)
print("extracted_rule_based_model: [\n")
lib.tree_to_ruleset(clf, fn, [class_attr, neg_class_attr])
print("\n]")