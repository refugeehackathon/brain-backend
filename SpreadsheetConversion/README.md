Prepare DB Tables
=================

There are two IPython Notebooks in this directory. First, `categories.ipynb`
should be run which parses the category Google spreadsheet (currently only in
German). Second, `convert_tables_for_db.ipynb` parses the 'Project-DB' table
and splits it up into separate tables as required for the database schema.

The splitting process is currently incomplete. The categories need a lot of work
before they can be merged between the two tables. Also, the way that contact
information work is unclear to me right now.

The file `requirements.txt` can be passed to the Python `pip` program in order to
install all the necessary packages to run these files.

```
pip install -r requirements.txt
```

Feel free to ping me in slack chat @midnighter.
