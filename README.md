bulk-parser-for-browscap
========================

Bulk input data parser for browscap. Accepts a list of user agents with weights and outputs prioritized list of what user agents need to be included in the project.


Instructions
------------

Input file name format is `*.txt`. They can optionally be packed to `*.tar.gz` file. Each input file is parsed only once (info about already parsed files is saved in `already-parsed.txt`), so you don't have to remove old files.

Input files have records separated by new lines. Record format is `<numeric-weight><tabulator><user-agent-string>`. Records have to be sorted by weight descending. New line types don't matter.

**Put your files in "imports/" folder and run "php bulk-browscap-parse.php".** Data from each file will be parsed and integrated with other files. Then all user agents will be checked in `get_browser()`. Results will be saved in `user-agents-to-parse.txt` file.

Results file `user-agents-to-parse.txt` has the same format as other files. It contains only the user agents that were not recoginsed by browscap, sorted from most important to least important. This file will be loaded each time you parse new source data files. The new results will be added to the old ones. This way if some user agents were present in files parsed before and are still not added to browscap they will gain more and more weight.

The weights used are normalized. It doesn't matter what are the max and min weight in the input files as long as the data in those files is correctly sorted.
