#!/bin/env python

"""Used to diff a development folder with sym-files

Will not show diffs for files that are sym links and files that are similar
"""

from __future__ import print_function

import subprocess
import argparse
from os import listdir, path


def main():
    """Main function"""
    parser = argparse.ArgumentParser(description='Diff development folder with original.')
    parser.add_argument('folder', help='the name of the development folder')
    
    args = parser.parse_args()

    folder = args.folder.strip("/")

    symlinks = []
    no_diff = []
    diffs = []
    new_files = []
    for file_ in listdir(folder):
        # Only check python and php files
        if path.splitext(file_)[1] not in ('.py', '.php'):
            continue
        # Skip symfiles
        if path.islink(file_):
            symlinks.append(file_)
            continue

        # Check that there is a file to diff against
        if not path.isfile("sym-files2/{0}".format(file_)):
            new_files.append(file_)
            continue

        result = subprocess.Popen(
            "diff -u sym-files2/{0} {1}/{0}".format(file_, folder),
            stdout=subprocess.PIPE,
            shell=True,
            )
        output = result.communicate()

        # returncode 1 means that there is a diff, 0 means no diff
        if result.returncode == 1:
            diffs.append((file_, output))
        elif result.returncode == 0:
            no_diff.append(file_)
        else:
            raise RuntimeError("Unexpected returncode")
            

    def out(file_, result):
        print("{0:.<38} {1}".format(path.join(folder, file_) + " ", result))

    for file_ in symlinks:
        out(file_, "symlink")
    for file_ in no_diff:
        out(file_, "identical")
    for file_ in new_files:
        out(file_, "new")
    for file_, diff in diffs:
        out(file_, "HASS DIFF")

    
    for file_, diff in diffs:
        print("\n### {0:#<67}".format(file_ + " "))
        print(diff[0])
        


main()
