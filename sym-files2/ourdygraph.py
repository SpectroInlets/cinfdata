#!/usr/bin/python3
# pylint: disable=I0011,R0902,C0103,R0903

"""
This file is part of the CINF Data Presentation Website
Copyright (C) 2012 Robert Jensen, Thomas Andersen and Kenneth Nielsen

The CINF Data Presentation Website is free software: you can
redistribute it and/or modify it under the terms of the GNU
General Public License as published by the Free Software
Foundation, either version 3 of the License, or
(at your option) any later version.

The CINF Data Presentation Website is distributed in the hope
that it will be useful, but WITHOUT ANY WARRANTY; without even
the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more
details.

You should have received a copy of the GNU General Public License
along with The CINF Data Presentation Website.  If not, see
<http://www.gnu.org/licenses/>.
"""

import time
import sys
import uuid
import json
from common import Color


def bool_str(string):
    """Convert false and true strings to bool"""
    if string == "true":
        return True
    elif string == "false":
        return False
    else:
        raise ValueError('Bool value must be "true" or "false"')


class Plot():
    """ This class is used to generate the dygraph content.

    NOTE: With the version of dygraph used at the time of writing
    version 2 (medio 2012) it was not possible to produce parametric
    plots (for as function of temperature type plots) nor was it
    possible to flip the x axis be reversing the x-axis limits. For
    the latter there is a snippet of code in a comment in the end of
    the file if it is ever made possible."""

    def __init__(self, options, ggs):
        """ Initialize variables """
        self.o = options
        self.ggs = ggs # Global graph settings

        self.right_yaxis = len(self.o['right_plotlist']) > 0
        self.out = sys.stdout
        self.tab = '    '
        self.measurement_count = None
        # Colors object, will be filled in at new_plot
        self.c = None
        self.reduction = 1

    def new_plot(self, data, plot_info, measurement_count):
        """ Produce all the plot output by calls to the different
        subsection methods
        """
        self.c = Color(data, self.ggs)
        self.measurement_count = sum(measurement_count)
        self._header()
        self._data(data)
        self._options(data, plot_info)
        self._end()
        
    def _header(self):
        """ Form the header """
        # Get max points from settings of any, default 10000
        #max_points = 100000
        max_points = 50000
        if self.ggs.get('dygraph_settings') is not None:
            if self.ggs['dygraph_settings'].get('max_points') is not None:
                max_points = int(self.ggs['dygraph_settings']['max_points'])
        # Calculate the data point reduction factor
        
        self.reduction = int(self.measurement_count / max_points) + 1

        if self.reduction > 1:
            mouse_over = "This plot, which was supposed to contain {0} data "\
                "points, has been reduced to only plot every {1} point, to "\
                "reduce the total data size. The current data point limit is "\
                "{2} and it can be changed in your graphsettings.xml"
            mouse_over = mouse_over.format(self.measurement_count,
                                           self.reduction, max_points)
            warning = '<b title=\\"{0}\\">Reduced data set (hover for '\
                'details)</b>'.format(mouse_over)
            self.out.write('document.getElementById("warning_div").innerHTML='\
                               '\"{0}\";'.format(warning))

        # Write dygraph header
        self.out.write('g = new Dygraph(\n' +
                       self.tab + 'document.getElementById("graphdiv"),\n') 

    def _data(self, data):
        """ Determine the type of the plot and call the appropriate
        _data_*** function
        """
        # Generate a random filename for the data
        filename = '../figures/{0}.csv'.format(uuid.uuid4())
        self.out.write('{0}"{1}",\n'.format(self.tab, filename))
        file_ = open(filename, 'w')
        if self.ggs['default_xscale'] == 'dat':
            self._data_dateplot(file_, data)
        else:
            self._data_xyplot(file_, data)
        file_.close()

    def _data_dateplot(self, out, data):
        """Generate the CSV data for a dateplot"""
        # Total number of plots
        plot_number = len(self.o['left_plotlist'] + self.o['right_plotlist'])
        # Loop over data series
        for n, dat in enumerate(data['left'] + data['right']):
            this_line = [''] * (plot_number + 1)
            # Loop over points in that series
            for index, item in enumerate(dat['data']):
                if index % self.reduction == 0:
                    # Insert date in column 0 and y in appropriate column
                    this_line[0] = str(time.strftime(
                            '%Y-%m-%d %H:%M:%S', time.localtime(int(item[0]))
                            ))
                    this_line[n+1] = str(item[1])
                    out.write(','.join(this_line) + '\n')

        # Write one bogus points if there is no data
        if self.measurement_count == 0:
            out.write('42,42\n')

    def _data_xyplot(self, out, data):
        """Generate the CSV data for a XY plot"""
        # Total number of plots
        plot_number = len(self.o['left_plotlist'] + self.o['right_plotlist'])
        # Loop over data series
        for n, dat in enumerate(data['left'] + data['right']):
            this_line = [''] * (plot_number + 1)
            # Loop over points in that series
            for index, item in enumerate(dat['data']):
                if index % self.reduction == 0:
                    # Insert x in column 0 and y in appropriate column
                    this_line[0] = str(item[0])
                    this_line[n+1] = str(item[1])
                    out.write(','.join(this_line) + '\n')

        # Write one bogus points if there is no data
        if self.measurement_count == 0:
            out.write('42,42\n')

    def _options(self, data, plot_info):
        """Form all the options and output as JSON"""
        # Form labels string
        labels = [self.ggs['xlabel'] if 'xlabel' in self.ggs else '']
        for dat in data['left'] + data['right']:
            labels.append(dat['lgs']['legend'])
        # Overwrite labels if there is no data
        if self.measurement_count == 0:
            labels = ['NO DATA X', 'NO DATA Y']

        # Initiate options variable.
        options = {
            'labels': labels,
            'connectSeparatedPoints': True,
            'legend': 'always',
            }

        # FIXME WORK-AROUND: There is a bug in the new dygraphs, so
        # that if logscale is to be used on any of the axis, it must
        # be set to true as a top level option as well as in the axis
        # specific options:
        # https://github.com/danvk/dygraphs/issues/867
        if self.o['left_logscale'] or self.o['right_logscale']:
            options['logscale'] = True

        # Form the series specific options. Looks like:
        #"series": {
        #    "M4": {
        #        "color": "#63650b", 
        #        "axis": "y"
        #    }, 
        #    "Gas flow 1 [He] (r)": {
        #        "color": "#3b3914", 
        #        "axis": "y2"
        #    }, 
        #}
        series = {}
        for side, axis in (('left', 'y'), ('right', 'y2')):
            for dat in data[side]:
                series[dat['lgs']['legend']] = {
                    'axis': axis,
                    'color': self.c.get_color_hex(),
                }
        options['series'] = series

        # Add axes options
        self._options_axes(options, plot_info)

        # Add title, and axis labels
        self._options_title_axes_labels(options, plot_info)

        # X-scale
        if self.o['xscale_bounding'] is not None and\
                self.o['xscale_bounding'][1] > self.o['xscale_bounding'][0]:
            options['dateWindow'] = self.o['xscale_bounding']

        replacements = self._options_from_graphsettings(options)

        # Generate options, replace function placeholder and write out
        options_text = json.dumps(options, indent=4) + '\n'
        for placeholder, replacement in replacements.items():
            options_text = options_text.replace(placeholder, replacement)
        self.out.write(options_text)


    def _options_axes(self, options, plot_info):
        """Form the axes options, will modify options variable"""
        axes = {
            'x': {'drawGrid': True},
            'y': {
                'logscale': self.o['left_logscale'],
                'drawGrid': False,
                },
            }
        # Zoom, left y-scale
        if self.o['left_yscale_bounding'] is not None:
            axes['y']['valueRange'] = self.o['left_yscale_bounding']

        # Add second yaxis configuration
        if self.right_yaxis:
            axes['y2'] = {
                'logscale': self.o['right_logscale'],
                'drawGrid': False,
                }
            if self.o['right_yscale_bounding'] is not None:
                axes['y2']['valueRange'] = self.o['right_yscale_bounding']

            # Add the right y label
            if 'right_ylabel' in plot_info:
                if plot_info['y_right_label_addition'] == '':
                    axes['y2']['axisLabelWidth'] = 80
                    y2label = plot_info['right_ylabel']
                else:
                    axes['y2']['axisLabelWidth'] = 100
                    y2label = '<font size="3">{0}<br />{1}</font>'.format(
                        plot_info['right_ylabel'], 
                        plot_info['y_right_label_addition'],
                        )
                options['y2label'] = y2label

        options['axes'] = axes

    def _options_title_axes_labels(self, options, plot_info):
        """Add title and axis labels"""
        # Add title
        if 'title' in plot_info:
            if self.measurement_count == 0:
                options['title'] = 'NO DATA'
            else:
                options['title'] = plot_info['title']

        axes = options['axes']
        # Add the left y label
        if 'left_ylabel' in plot_info:
            if plot_info['y_left_label_addition'] == '':
                axes['y']['axisLabelWidth'] = 80
                ylabel = plot_info['left_ylabel']
            else:
                axes['y']['axisLabelWidth'] = 100
                ylabel = '<font size="3">{0}<br />{1}</font>'.format(
                    plot_info['left_ylabel'],
                    plot_info['y_left_label_addition'],
                    )
            options['ylabel'] = ylabel

        # Determine the labels and add them
        if 'xlabel' in plot_info:
            if self.ggs['default_xscale'] != 'dat':
                if plot_info['xlabel_addition'] == '':
                    options['xlabel'] = plot_info['xlabel']
                else:
                    label = '<font size="3">{0}<br />{1}</font>'.format(
                        plot_info['xlabel'],
                        plot_info['xlabel_addition'],
                        )
                    options['xlabel'] = label
                    options.append({'xLabelHeight': '30'})

    def _options_from_graphsettings(self, options):
        """Add options from graphsettings"""
        replacements = {}
        # Add modifications from settings file
        if 'dygraph_settings' in self.ggs:
            # roller
            if 'roll_period' in self.ggs['dygraph_settings']:
                period = int(self.ggs['dygraph_settings']['roll_period'])
                options.update({'showRoller': 'true', 'rollPeriod': period})

            # grids
            for axis_name in 'xy':
                grid_settings_str = self.ggs['dygraph_settings']\
                    .get(axis_name + 'grid')
                if grid_settings_str is not None:
                    grid_settings = bool_str(grid_settings_str)
                    options['axes'][axis_name]['drawGrid'] = grid_settings

            # high light series
            if 'series_highlight' in self.ggs['dygraph_settings']:
                if self.ggs['dygraph_settings']['series_highlight'] == 'true':
                    options['highlightSeriesOpts'] = {
                        'strokeWidth': 2,
                        'strokeBorderWidth': 1,
                        'highlightCircleSize': 5,
                        }

            # Labels modifications
            labels_options = {}
            if 'labels_side' in self.ggs['dygraph_settings']:
                if self.ggs['dygraph_settings']['labels_side'] == 'true':
                    labels_options.update({
                            'labelsSeparateLines': True,
                            # func1 is just a placeholder that will
                            # be replaced by:
                            # document.getElementById("labels")
                            # in the serialized json, because json
                            # cannot serialize functions
                            'labelsDiv': '#func1#',
                            })
                    replacements['"#func1#"'] = \
                        'document.getElementById("labels")'

            sep_new_lines_str = self.ggs['dygraph_settings']\
                .get('labels_newline')
            if sep_new_lines_str is not None:
                labels_options['labelsSeparateLines'] = \
                    bool_str(sep_new_lines_str)
            options.update(labels_options)

        return replacements

    def _end(self):
        """ Output last line """
        self.out.write(');')
