import os
import requests
from requests.auth import HTTPBasicAuth
from collections import defaultdict

# 🔧 Fonction pour récupérer tous les sprints fermés
def fetch_all_closed_sprints(host, board_id, headers, auth):
    all_sprints = []
    start_at = 0
    max_per_page = 50

    while True:
        url = f"https://{host}/rest/agile/1.0/board/{board_id}/sprint"
        params = {
            "state": "closed",
            "startAt": start_at,
            "maxResults": max_per_page
        }
        response = requests.get(url, headers=headers, auth=auth, params=params)
        if response.status_code != 200:
            print("Erreur récupération des sprints :", response.status_code)
            break

        data = response.json()
        sprints = data.get("values", [])
        all_sprints.extend(sprints)

        if data.get("isLast", False) or len(sprints) == 0:
            break

        start_at += max_per_page

    return sorted(all_sprints, key=lambda s: s["id"], reverse=True)  # tri du plus récent au plus ancien


# 🔐 Auth
JIRA_EMAIL = os.environ.get("JIRA_EMAIL")
JIRA_TOKEN = os.environ.get("JIRA_TOKEN")
if not JIRA_EMAIL or not JIRA_TOKEN:
    raise Exception("Variables d'environnement JIRA_EMAIL ou JIRA_TOKEN manquantes.")

HOST = "studi-pedago.atlassian.net"
BOARD_ID = 1359
STORY_POINTS_FIELD = "customfield_10200"
IGNORED_USERS = {"Unassigned", "Guillaume Anthore", "Anaïs PRUD'HOMME", "vanessa.jaovelo", "Antoine LAY"}

headers = {"Accept": "application/json"}
auth = HTTPBasicAuth(JIRA_EMAIL, JIRA_TOKEN)

# 🌀 Récupération de tous les sprints
sprints_data = fetch_all_closed_sprints(HOST, BOARD_ID, headers, auth)
sprints_data = sprints_data[:6]  # Limiter aux 30 derniers sprints


# 🧠 Structures :
velocity_by_sprint = defaultdict(lambda: defaultdict(int))        # points par dev
tickets_by_dev_by_sprint = defaultdict(lambda: defaultdict(int)) # nb tickets par dev
total_points_by_sprint = {}                                       # total sprint

global_points_by_dev = defaultdict(int)        # points cumulés
global_sprint_count_by_dev = defaultdict(int)  # nb de sprints avec activité
total_tickets_by_dev = defaultdict(int)
sprints_with_ticket_by_dev = defaultdict(int)

for sprint in sprints_data:
    sprint_id = sprint["id"]
    sprint_name = sprint["name"]

    print(f"\n📦 Sprint: {sprint_name} (ID: {sprint_id})")

    jql = f'project = MD AND issuetype in (Bug, Epic, Story, Task, Technique) AND sprint = {sprint_id} AND statusCategory = Done'
    search_url = f"https://{HOST}/rest/api/2/search"
    payload = {
        "jql": jql,
        "fields": ["assignee", STORY_POINTS_FIELD],
        "maxResults": 1000
    }

    response = requests.post(search_url, headers=headers, json=payload, auth=auth)
    if response.status_code != 200:
        print(f"❌ Erreur pour sprint {sprint_name} : {response.status_code}")
        continue

    data = response.json()
    total_points = 0
    devs_in_sprint = set()

    for issue in data.get("issues", []):
        fields = issue["fields"]
        assignee = fields["assignee"]["displayName"] if fields["assignee"] else "Unassigned"
        if assignee in IGNORED_USERS:
            continue

        total_tickets_by_dev[assignee] += 1
        points = fields.get(STORY_POINTS_FIELD) or 0
        velocity_by_sprint[sprint_name][assignee] += points
        tickets_by_dev_by_sprint[sprint_name][assignee] += 1
        total_points += points
        devs_in_sprint.add(assignee)

    total_points_by_sprint[sprint_name] = total_points

    # Enregistrer participation pour le calcul de moyenne
    for dev in devs_in_sprint:
        global_points_by_dev[dev] += velocity_by_sprint[sprint_name][dev]
        global_sprint_count_by_dev[dev] += 1

    for dev in tickets_by_dev_by_sprint[sprint_name].keys():
        if tickets_by_dev_by_sprint[sprint_name][dev] > 0:
            sprints_with_ticket_by_dev[dev] += 1

# 📊 Affichage par sprint
print("\n✅ Récapitulatif complet par sprint :\n")
for sprint_name in velocity_by_sprint.keys():
    print(f"▶ Sprint: {sprint_name}")
    total = total_points_by_sprint[sprint_name]
    print(f"  Total story points : {total}")

    for dev in velocity_by_sprint[sprint_name].keys():
        points = velocity_by_sprint[sprint_name][dev]
        tickets = tickets_by_dev_by_sprint[sprint_name][dev]
        percent = round((points / total) * 100, 1) if total else 0.0
        print(f"  - {dev}: {points} pts ({percent}%), {tickets} ticket(s)")

# 📈 Moyenne par développeur
print("\n📈 Moyenne de story points par sprint (par développeur actif) :\n")
for dev, total_pts in global_points_by_dev.items():
    sprint_count = global_sprint_count_by_dev[dev]
    average = round(total_pts / sprint_count, 2) if sprint_count else 0
    print(f"- {dev} : {total_pts} pts sur {sprint_count} sprint(s) → moyenne : {average} pts/sprint")
    
print("\n📊 Moyenne de tickets par sprint (par développeur actif) :\n")
for dev, total_tickets in total_tickets_by_dev.items():
    active_sprints = sprints_with_ticket_by_dev[dev]
    avg_tickets = round(total_tickets / active_sprints, 2) if active_sprints else 0
    print(f"- {dev} : {total_tickets} tickets sur {active_sprints} sprint(s) → moyenne : {avg_tickets} tickets/sprint")
    

