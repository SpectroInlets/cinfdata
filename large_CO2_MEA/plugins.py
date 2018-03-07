
from __future__ import print_function

import numpy


class AreaNormalizer(object):
    """Normalize current to catalyst area"""

    def __init__(self, settings, plot_options, ggs=None):
        self.settings = settings
        self.label_additions = {
            'xlabel_addition': '',
            'y_left_label_addition': '',
            'y_right_label_addition': '',
        }

    def run(self, left, right):
        """The main run method"""

        # Ylabel addition template
        ylabel_addition = 'Current normalized to catalyst area: {0:.2f}cm2'

        # Loop over data set sides
        for side_name, side in (('left', left), ('right', right)):
            for data_set in side:
                metadata = data_set['meta']

                # Skip all data sets that aren't current
                if metadata['label'] != 'current':
                    continue

                # Extract area
                description = metadata['cathode_catalyst_description']
                description.split('size:')[1].split('cm2')[0]
                area = float(description.split('size:')[1].split('cm2')[0].strip())

                # Normalize data to current
                data_set['data'][:, 1] /= area
                self.label_additions['y_' + side_name + '_label_addition'] =\
                    ylabel_addition.format(area)

                # Output
                print("Dataset {id} normalized to: {0}".format(area, **metadata))

        return self.label_additions
