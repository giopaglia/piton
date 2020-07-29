# piton


DBFit(DB)
DBFit->test_all_capabilities()
DBFit->readData()
SQL: SELECT * FROM `information_schema`.`columns` WHERE `table_name` IN ('spam') 
SQL: SELECT Message FROM `spam`
SQL: SELECT Category, Message FROM `spam`
DBFit->updateModel()
DBFit->learnModel()
Ultimately, here are the extracted rules: 
0: ( ('call' in Message == "Y") and ('ur' in Message == "Y") and ('get' in Message == "N") and ('u' in Message == "Y") ) => [0]
1: ( ('call' in Message == "Y") and ('gt' in Message == "N") and ('go' in Message == "N") and ('get' in Message == "N") and ('come' in Message == "N") and ('ur' in Message == "Y") ) => [0]
2: ( ('call' in Message == "Y") and ('gt' in Message == "N") and ('go' in Message == "N") and ('m' in Message == "N") and ('dai' in Message == "N") and ('come' in Message == "N") ) => [0]
3: ( ('ur' in Message == "Y") and ('u' in Message == "N") and ('get' in Message == "Y") ) => [0]
4: ( ('ur' in Message == "Y") and ('u' in Message == "N") and ('come' in Message == "N") and ('gt' in Message == "N") and ('go' in Message == "Y") ) => [0]
5: ( ('ur' in Message == "Y") and ('u' in Message == "N") and ('come' in Message == "N") and ('gt' in Message == "N") and ('m' in Message == "Y") ) => [0]
6: (  ) => [1]
DBFit->test(Data{1115} instances; [Categor,'u' in ,'call' ,'go' in,'m' in ,'get' i,'ur' in,'gt' in,'lt' in,'come' ,'dai' i]})
DBFit->predict(Data{1115} instances; [Categor,'u' in ,'call' ,'go' in,'m' in ,'get' i,'ur' in,'gt' in,'lt' in,'come' ,'dai' i]})
Test accuracy: 0.8932735426009
The code took 10.912052154541 seconds to complete.