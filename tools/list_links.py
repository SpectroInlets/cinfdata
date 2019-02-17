from __future__ import print_function

from os import walk, path

# traverse root directory, and list directories as dirs and files as files
for root, dirs, files in walk("/var/www/cinfdata"):
    for file_ in files:
        filepath = path.join(root, file_)
        if path.islink(filepath):
            print(filepath, '->', path.realpath(filepath))
