


# EDIT point start
INDENT = '    '
TABLE = 'dateplots_gassupply'
CODENAMES = [
    'gassupply_307_ch4_right',
    'gassupply_307_ch4_left',
    'gassupply_307_h2_right',
    'gassupply_307_h2_left',
    'gassupply_307_CO_left',
    'gassupply_307_CO_right',
    'gassupply_307_Ar_right',
    'gassupply_307_Ar_left',
    'gassupply_307_O2_right',
    'gassupply_307_O2_left',
    'gassupply_307_N2_right',
    'gassupply_307_N2_left',
    'gassupply_307_He_right',
    'gassupply_307_He_left',
]

def codename_to_title(codename):
    parts = codename.split('_')
    return parts[2] + ' ' + parts[3].title()

def codename_to_legend(codename):
    return codename_to_title(codename)

def codename_to_ylabel(codename):
    return 'Pressure / bar'



# EDIT point end



template = """
{indent}<dateplot{num}>
{indent}  <title>{title}</title>
{indent}  <legend>{legend}</legend>
{indent}  <query>SELECT unix_timestamp(time), value FROM {table} WHERE type = (SELECT id FROM dateplots_descriptions where codename="{codename}") and time between "{{from}}" and "{{to}}" order by time</query>
{indent}  <ylabel>{ylabel}</ylabel>
{indent}  <color>{num}</color>
{indent}</dateplot{num}>""".strip()



for num, codename in enumerate(CODENAMES, 1):
    out = template.format(
        codename=codename,
        title=codename_to_title(codename),
        legend=codename_to_legend(codename),
        ylabel=codename_to_ylabel(codename),
        num=num,
        table=TABLE,
        indent=INDENT,
        )
    print(out)
