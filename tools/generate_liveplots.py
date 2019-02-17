
# EDIT point start
TYPE = 'table'
INDENT = '	  '
TABLE = 'dateplots_gassupply'
DATA_SOURCE = 'rasppi99'
ITEMS = [
    ('gassupply_307_ch4_right', "#77583A"),
    ('gassupply_307_ch4_left', "#77583A"),
    ('gassupply_307_h2_right', "#FF0000"),
    ('gassupply_307_h2_left', "#FF0000"),
    ('gassupply_307_CO_left', "#009933"),
    ('gassupply_307_CO_right', "#009933"),
    ('gassupply_307_Ar_right', "#0000FF"),
    ('gassupply_307_Ar_left', "#0000FF"),
    ('gassupply_307_O2_right', "#CCCC00"),
    ('gassupply_307_O2_left', "#CCCC00"),
    ('gassupply_307_N2_right', "#990099"),
    ('gassupply_307_N2_left', "#990099"),
    ('gassupply_307_He_right', "#112233"),
    ('gassupply_307_He_left', "#112233"),
]

def codename_to_label(codename):
    parts = codename.split('_')
    return parts[2] + ' ' + parts[3].title()




# EDIT point end

# rasppi15:B312_gasalarm_CO_pumproom


figure_template = """
{indent}<plot{num}>
{indent}  <data_channel>{datachannel}</data_channel>
{indent}  <label>{label}</label>
{indent}  <color>{color}</color>
{indent}  <old_data_query>select unix_timestamp(time), value from {table} where type=(SELECT id FROM dateplots_descriptions where codename="{codename}") and unix_timestamp(time) > {{from}}</old_data_query>
{indent}</plot{num}>""".strip()


table_template = """
{indent}<item{num}>
{indent}  <data_channel>{datachannel}</data_channel>
{indent}  <label>{label}</label>
{indent}  <color>{color}</color>
{indent}  <format>.1f</format>
{indent}  <unit>bar</unit>
{indent}</item{num}>""".strip()


for num, (codename, color) in enumerate(ITEMS):
    if TYPE == 'table':
        out = table_template.format(
            datachannel=DATA_SOURCE + ':' + codename,
            codename=codename,
            color=color,
            num=num,
            indent=INDENT,
            label=codename_to_label(codename),
            )
    else:
        out = figure_template.format(
            datachannel=DATA_SOURCE + ':' + codename,
            codename=codename,
            color=color,
            label=codename_to_label(codename),
            num=num,
            table=TABLE,
            indent=INDENT,
            )
    print(out)
