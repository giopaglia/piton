import pandas as pd
import math as mt
from io import StringIO 
import sys

class Capturing(list):  # and object of class Capturing can direct an output to a list
    def __enter__(self):
        self._stdout = sys.stdout
        sys.stdout = self._stringio = StringIO()
        return self
    def __exit__(self, *args):
        self.extend(self._stringio.getvalue().splitlines())
        del self._stringio    # free up some memory
        sys.stdout = self._stdout

def object_attrs_to_cat(df):    # for evey attribute in the dataframe, if it's of type object, it converts it to categorical
    for column_name in df:
        if df[column_name].dtypes == "object": # trova un modo per fare questo
            df[column_name] = pd.Categorical(df['class'])

def df_try(df):
    print(df.head())    # prints first 5 rows of the dataframe
    print(df.dtypes)    # prints the attribute type for every attribute in the dataframe;
                        # categorical attributes are of type object

def get_class_attr(df):
    return df.columns[-2]

def is_binary(attr):            # checks if a categorical attribute is binary
                                # what if it contains just one attribute?
                                # I consider it binary (all tuples have the same value)
    return attr.nunique() <= 2

def print_dataframe(df):    # prints all the dataframe rows
    pd.set_option('display.max_rows', df.shape[0]+1)
    print(df)


# I want to eliminate all columns that contains more than % of the data as NaN
# Then I remove every row which contains a null data for a numeric attribute
def clean_dataframe(df, cp):
    th = mt.ceil((1-cp)*len(df.index))
    print("removing cols with more than " + str(len(df.index) - th) + " NaN values\n")
    na_free = df.dropna(axis='columns', thresh=th)
    only_na = df.columns[~df.columns.isin(na_free.columns)]
    for column_name in only_na:
        print(column_name + " has been removed")
    print(str(len(only_na)) + " columns have been removed\n")    
    df = na_free
    df = clear_numeric_nan(df)
    return df

def clear_numeric_nan(df):  # removes every row which contains a NaN value for a numeric attribute
    for column_name in df:
        if df[column_name].dtypes == "float64":     # for every numeric attribute
            na_free = df.dropna(subset=[column_name]) # drop row if that numeric attribute contains a NaN value
            only_na = df[~df.index.isin(na_free.index)]
            if len(only_na.index) != 0:
                print(str(len(only_na.index)) + " rows have been removed, NaN value found in numeric attribute: " + column_name)
            df = na_free
    print("\n")
    return df

def clear_categorical_nan(df):  #removes every row echich contains a NaN value for a categorical attribute
    for column_name in df:
        if df[column_name].dtypes == "object":      # for every categorical attribute
            na_free = df.dropna(subset=[column_name])   # drop row if that categorical attribute contains a NaN value
            only_na = df[~df.index.isin(na_free.index)]
            if len(only_na.index) != 0:
                print(str(len(only_na.index)) + " rows have been removed, NaN value found in numeric attribute: " + column_name)
            df = na_free
    print("\n")
    return df

def replace_words(s, words):    # given a string s, replaces the key dictionary words with respective dictionary values
    for k, v in words.items():
        s = s.replace(k, v)
    return s

def contains_val(df, attr, val):    # returns True if the attribute of the df contains the val, otherwise it returns False
    class_attr = get_class_attr(df)
    class_values = df[class_attr].unique()
    val = [i for i in class_values if i == class_attr]
    if val:
        return True
    else:
        return False

def get_negative_class_value(df):   # gives the negative class attribute
    class_attr = get_class_attr(df)
    class_values = df[class_attr].unique()
    neg_class_val = [i for i in class_values if i != class_attr]
    return neg_class_val[0]
