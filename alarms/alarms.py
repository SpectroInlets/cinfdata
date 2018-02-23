#!/usr/bin/python
# pylint: disable=star-args

"""Script that checks the user defined alarms on logged values

The alarms are defined in the alarms table. Which among other things
contains a list of queries and a list of parameters. Each of the
quiries must return rows on the form (unix_timestamp, value). The
quiries must return exactly 1 row except if used for dqdt. The checks
that can be performed are the following:

Greater than: s0 > s1
Less than: s0 < s1

Where s are the substitution markers. They can either be:
 * q0, q1, ... which are query numbers
 * v0, v1, ... which are parameter numbers (values), they can also be written
   as q#
 * dqdt0 which is the slope of the points in query 0

The syntax allows for 0 or 1 spaces between the parts, which means
that both "s0 > s1" and "s0>s1" is valid, but not "s0 >  s1".

The expression can be chained together with "and" or "or" as such:

s0 < s1 and s2 > s3

Examples of complete check could be:

q0 < v0
q0 < v0 and q1 > v1

"""


from __future__ import print_function
import re
import os
import sys
import json
import time
import smtplib
from email.mime.text import MIMEText
from collections import defaultdict, namedtuple
import logging
from logging.handlers import RotatingFileHandler
import numpy as np
import psutil
import MySQLdb

# Regular expression used to match the check, which are in the form:
# "q0 < p0" or "dqdt0 < p0"
CHUNKS = re.compile(r'>=|<=|==|!=|<|>|istrue|isfalse|and|or|'
                    r'dqdt[0-9]{1,3}|[vpq][0-9]{1,3}| *')
REPLACEMENTS = [
    (re.compile(r'(dqdt[0-9]{1,3}|[vpq][0-9]{1,3})'), r'{\1}'),
    (re.compile(r'(istrue)'), r'== 1'),
    (re.compile(r'(isfalse)'), r'== 0'),
]


# pylint: disable=too-many-arguments, too-many-locals
def get_logger(name, level='INFO', terminal_log=True, file_log=False,
               file_name=None, file_max_bytes=1048576, file_backup_count=3):
    """Copy from PyExpLabSys.common.utilities. See that module for details."""
    # Get the root logger and set the level
    log_level = getattr(logging, level.upper())
    root_logger = logging.getLogger('')
    root_logger.setLevel(log_level)

    handlers = []
    # Form the handler(s) and set the level
    if terminal_log:
        stream_handler = logging.StreamHandler()
        stream_handler.setLevel(log_level)
        handlers.append(stream_handler)

    # Create rotating file handler
    if file_log:
        if file_name is None:
            file_name = name + '.log'
        file_handler = RotatingFileHandler(file_name, maxBytes=file_max_bytes,
                                           backupCount=file_backup_count)
        file_handler.setLevel(log_level)
        handlers.append(file_handler)

    # Add formatters to the handlers and add the handlers to the root_logger
    formatter = logging.Formatter(
        '%(asctime)s:%(name)s: %(levelname)s: %(message)s')
    for handler in handlers:
        handler.setFormatter(formatter)
        root_logger.addHandler(handler)

    # Create a named logger and return it
    logger = logging.getLogger(name)
    return logger


_LOG = get_logger(__file__, level='debug', file_log=True,
                  file_name='/var/www/cinfdata/alarms/alarm_log',
                  terminal_log=False)


class ErrorDuringCheck(Exception):
    """Exception for a bad check definition"""
    pass


# pylint: disable=too-few-public-methods
class CheckAlarms(object):
    """Class that runs the alarm checks"""

    def __init__(self, dbmodule=MySQLdb):
        _LOG.debug('__init__(dbmodule={0})'.format(dbmodule))
        _db = dbmodule.connect(host='servcinf-sql', user='alarm',
                               passwd='alarm', db='cinfdata')
        self._alarm_cursor = _db.cursor()
        _db = dbmodule.connect(host='servcinf-sql', user='cinf_reader',
                               passwd='cinf_reader', db='cinfdata')
        self._reader_cursor = _db.cursor()
        self._smtp_server_address = '127.0.0.1'

    def _get_alarms(self):
        """Get the list of alarms to check"""
        _LOG.debug('_get_alarms()')
        fields = ('id', 'quiries_json', 'parameters_json', 'check',
                  'no_repeat_interval', 'message', 'recipients_json',
                  'subject')
        # Column names needs to be excaped with backticks, because
        # someone was stupid enough to pick one which is a reserved
        # word (check)
        fields_string =' ,'.join(['`{0}`'.format(field) for field in fields])
        query = 'SELECT {0} FROM alarm WHERE visible=1 AND active=1'.format(fields_string)
        self._alarm_cursor.execute(query)

        # Turns column names and rows into dict and decode json
        alarms = []
        for rows in self._alarm_cursor.fetchall():
            alarm = defaultdict(str)
            for name, value in zip(fields, rows):
                if name.endswith('_json'):
                    try:
                        parsed_value = json.loads(value)
                        if not isinstance(parsed_value, list):
                            alarm += '\n\nColumn {0} does not contain an JSON '\
                                     'encoded list'.format(name)
                        alarm[name.replace('_json', '')] = parsed_value

                    except ValueError:
                        alarm['error'] += \
                            '\n\nCould not decode json string '\
                            '"{0}" in column {1}'.format(value, name)
                else:
                    alarm[name] = value

                if name == 'parameters_json':
                    for parameter in alarm['parameters']:
                        if not isinstance(parameter, (float, int)):
                            alarm['error'] += \
                                '\n\nParameters must be floats or ints not {0}'.\
                                format(type(parameter).__name__)

            alarms.append(alarm)

        return alarms

    def check_alarms(self):
        """Checks the alarms and sends out emails if necessary"""
        _LOG.debug("check_alarms()")
        alarms = self._get_alarms()
        for alarm in alarms:
            _LOG.debug('Check alarm: {0}, {1}'.format(alarm['id'], dict(alarm)))
            if 'error' in alarm:
                # Send email about error parsing the json
                body = (
                    'At least one error occurred while trying to parse the '\
                    'alarm. The error message(s) was:{0}\n\n'
                    'The entire (half parsed) alarm definition was:\n\n{1}'
                ).format(alarm['error'], dict(alarm))

                # When the recipients JSON is broken, we cannot send them an
                # email about it! Send it to Robert and Kenneth instead.
                if 'recipients' not in alarm:
                    alarm['recipients'] = ['pyexplabsys-error@fysik.dtu.dk']
                    subject = 'Error in parsing recipients alarm JSON'
                    body += '\n\nTHIS EMAIL WAS ONLY SENT TO YOU'
                else:
                    subject = 'Error in parsing alarm'

                _LOG.info('Error in alarm parsing. Alarm: {0}'.format(alarm))
                self._send_email(subject, body, alarm['recipients'])

                continue

            # We need the check broken down into tokens to form all the arguments
            alarm['check_tokens'] = CHUNKS.findall(alarm['check'])

            # Try and check a single alarm
            try:
                # Get all the arguments that should be formatted into the check
                arguments = self._collect_arguments(alarm)

                # Perform the actual check
                status, check_string = self._check_single_alarm(
                    alarm,
                    arguments,
                )
            except ErrorDuringCheck as exp:
                subject = 'Error during check of alarm'
                body = 'An error was encountered during check of an alarm. '\
                       'The complete alarm definition is:\n{0}\n\nThe error '\
                       'was:\n{1}'.format(dict(alarm), repr(exp))
                _LOG.debug('Error during check: {0}'.format(repr(exp)))
                self._send_email(subject, body, alarm['recipients'])
                continue

            # Warn if necessary
            if status:
                _LOG.debug("Raise alarm for check string {0}".format(check_string))
                self._raise_alarm(alarm, check_string, arguments)
            else:
                _LOG.debug("No alarm for chech string {0}".format(check_string))

    def _check_single_alarm(self, alarm, arguments):
        _LOG.debug('_check_single_alarm(alarm={0}, arguments={1}")'\
                       .format(alarm, arguments))

        # Check whether all parts of the check was understood
        check_tokens = alarm['check_tokens']
        check = alarm['check']
        if ''.join(check_tokens) != check:
            message = ('Not all parts of the check string was fully understood. '
                       'The following tokens are allowed in the check: '
                       '>=, <=, ==, !=, <, >, istrue, isfalse, and, or, '
                       'dqdt#, v#, p#, q# and whitespace, where # is an integer'
                       '\n\n'
                       'Approved tokens: {0}\n'
                       'Full check string: {1}'
                       ).format(check_tokens, check)
            raise ErrorDuringCheck(message)

        # Perform check replacements, like q0 -> {q0}, istrue -> == 1
        for regular_expression, replacement in REPLACEMENTS:
            check = regular_expression.sub(replacement, check)

        # Format in all the arguments
        try:
            check = check.format(**arguments)
        except KeyError as exception:
            message = ('Not all placeholders in check was replaced by values. '
                       'The following placeholders was missing '
                       'arguments: {0}').format(repr(exception))
            raise ErrorDuringCheck(message)

        # Evaluate the check. This should be safe, since all parts of the check
        # string is curated or controlled by this program
        try:
            result = eval(check)
        except Exception as exception:
            message = ('An error accoured during evaluation of check. '
                       'The check was: {0}\nThe error was: {1}').\
                       format(check, repr(exception))
            raise ErrorDuringCheck(message)

        return result, check

    def _raise_alarm(self, alarm, check_string, arguments):
        """Raises an alarm, if not inhibited by no_repeat_interval"""
        _LOG.debug('_raise_alarm(%s, %s, %s)', alarm, check_string, arguments)
        last_alarm_time = self._get_time_of_last_alarm(alarm['id'])

        # Alarm is not inhibited by no_repeat_interval
        if last_alarm_time is None or time.time() - last_alarm_time > alarm['no_repeat_interval']:
            if last_alarm_time is None:
                _LOG.debug('No previous alarm within no_repeat_interval. '
                           'No previous alarm.')
            else:
                diff = time.time() - last_alarm_time
                _LOG.debug('No previous alarm within no_repeat_interval. Time '
                           'since last: {0:.1f}'.format(diff))

            query = "INSERT INTO alarm_log (alarm_id) VALUES (%s)"
            self._alarm_cursor.execute(query, (alarm['id']))

            subject = alarm['subject']
            if subject == '':
                subject = 'Surveillance alarm'
            body = '{message}\n\n'\
                '############### AUTO-GENERATED:\nThe check was: {check}\n'\
                'and the check string was: {0}'.format(check_string, **alarm)

            self._send_email(subject, body, alarm['recipients'], arguments)
        else:
            _LOG.debug('Previous alarm {0:.1f} seconds ago. Do not send an '
                       'email this time'.format(time.time() - last_alarm_time))

    def _get_time_of_last_alarm(self, alarm_id):
        """Returns the time of last alarm for alarm_id"""
        _LOG.debug('_get_time_of_last_alarm(alarm_id={0})'.format(alarm_id))
        query = 'select unix_timestamp(time) from alarm_log where alarm_id = '\
                '%s order by time desc limit 1;'
        self._alarm_cursor.execute(query, (alarm_id))
        result = self._alarm_cursor.fetchall()
        if len(result) == 0:
            return None
        else:
            # First column of first (and only) result
            return result[0][0]

    def _collect_arguments(self, alarm):
        """Collect all arguments for the check"""
        arguments = {}

        # Parameters
        for number, parameter in enumerate(alarm['parameters']):
            arguments['p{0}'.format(number)] = parameter
            arguments['v{0}'.format(number)] = parameter

        # Quiries
        quiries = alarm['quiries']
        for number, query in enumerate(quiries):
            query_token_name = 'q{0}'.format(number)
            try:
                value = self._query(query)
            except ErrorDuringCheck:
                if 'dqdt{0}'.format(number) in alarm['check_tokens']:
                    continue  # Ok, a query for dqdt
                else:
                    raise
            arguments[query_token_name] = value[1]  # query returns unix_time, value

        # From dqdt
        for token in alarm['check_tokens']:
            if not token.startswith('dqdt'):
                continue
            query_number = int(token[4:])
            try:
                query = quiries[query_number]
            except IndexError:
                message = 'Bad query index {0}, expected number below {1}'\
                          .format(query_number, len(quiries))
                raise ErrorDuringCheck(message)
            rows = self._query(query, single=False)
            if len(rows) < 2:
                message = 'The slope query returned less than 2 results'
                raise ErrorDuringCheck(message)
            x, y = zip(*rows)
            try:
                # polyfit returns coeffs. from order of decreasing power
                # so first item in 1st degree fit is the slope
                arguments[token] = np.polyfit(x, y, 1)[0]
            except Exception as exp:
                message = 'An error happened during the linear regression.'\
                    ' The error message was: {0}'.format(repr(exp))
                raise ErrorDuringCheck(message)

        return arguments

    def _query(self, query, single=True):
        """Fetches values for a query"""
        _LOG.debug('_query("{0}", single={1})'.format(query, single))
        try:
            self._reader_cursor.execute(query)
            rows = self._reader_cursor.fetchall()
        except MySQLdb.Error as exp:
            message = (
                "An error occured during execution of the SQL query '{0}'. "
                "The error was: {1}".format(query, repr(exp))
                )
            raise ErrorDuringCheck(message)

        # Check that there are rows in the result
        if len(rows) == 0:
            message = 'The query "{0}" produced 0 rows of results'.format(query)
            raise ErrorDuringCheck(message)

        # Check that results are on the (unixtime, value) form
        for row in rows:
            if len(row) != 2:
                message = 'The query "{0}" produced results, where the number '\
                          'of columns is not 2. The results must always be '\
                          'on the (unixtime, value) form'.format(query)
                raise ErrorDuringCheck(message)

        # If only a single row is expected
        if single:
            if len(rows) > 1:
                message = 'The query "{0}" produced more than 1 row of '\
                          'results, which is the expected'.format(query)
                raise ErrorDuringCheck(message)
            return rows[0]
        else:
            return rows

    def _send_email(self, subject, body, recipients, arguments=None):
        """Sends an email with the specified content"""
        _LOG.debug('_send_email(subject="{0}", body="{1}...", recipients={2})'
                   ''.format(subject, body.split('\n')[0], recipients))

        if arguments:
            try:
                body = body.format(**arguments)
            except Exception as exp:  # We don't want to risk this not being sent
                message = (
                    "An error occured while trying to format arguments into "
                    "the email body. The original body is preserved below. \n"
                    "The error was: {0}\n"
                    "The arguments were: {1}\n"
                    "---------------------------------------------------------\n"
                    ).format(repr(exp), arguments)
                body = message + body

        msg = MIMEText(body)
        # Header info
        msg['Subject'] = subject
        msg['From'] = 'no-reply@fysik.dtu.dk'
        msg['To'] = ', '.join(recipients)
        msg['Reply-To'] = ', '.join(recipients)

        # Send the message via our own SMTP server
        attempts = 0
        while attempts < 3:
            try:
                smtp_server = smtplib.SMTP(self._smtp_server_address)
                smtp_server.sendmail('no-reply@fysik.dtu.dk', recipients, msg.as_string())
                smtp_server.quit()
                break
            except smtplib.SMTPException:
                attempts += 1
                time.sleep(10)
        else:
            raise IOError('Unable to send email')


def main():
    """Main method"""
    _LOG.info('Script started')
    start_time = time.time()

    try:
        with open('/tmp/alarms-already-running') as file_:
            old_pid = int(file_.read().strip())
    except (IOError, ValueError):
        old_pid = -1

    # Check whether the last instance has finished
    if psutil.pid_exists(old_pid):
        _LOG.info('Old instance with pid {0} still running, abort!'.format(old_pid))
        print('Old instance with pid {0} still running, abort!'.format(old_pid))
        return

    # Write the lock file
    with open('/tmp/alarms-already-running', 'w') as file_:
        pid = os.getpid()
        _LOG.debug('Writing pid file with pid: {0}'.format(pid))
        file_.write(str(pid))


    check_alarms = CheckAlarms()
    try:
        check_alarms.check_alarms()
    except Exception as exp:
        _LOG.exception("An error occoured during alarm script")
        check_alarms._send_email("Alarm script generated error",
                                 str(exp.args),
                                 ['knielsen@fysik.dtu.dk'])

    _LOG.debug('Execution time: {0}'.format(time.time() - start_time))

if __name__ == '__main__':
    main()
