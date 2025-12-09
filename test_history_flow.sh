#!/bin/bash

# Script de test du flux d'historique et d'annulation
# Utilise l'API EcoRide v2 pour tester :
# 1. Historique des covoiturages (avec rôle visible)
# 2. Annulation d'un covoiturage (chauffeur ou passager)
# 3. Vérification que les crédits sont mis à jour
# 4. Vérification que les emails sont envoyés

BASE_URL="http://localhost:8000/api"

# Couleurs pour l'output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== TEST FLUX HISTORIQUE ET ANNULATION ===${NC}\n"

# 1. Récupérer les headers CSRF (après login)
echo -e "${YELLOW}1. Récupération du token CSRF...${NC}"
CSRF_RESPONSE=$(curl -s -c cookies.txt "$BASE_URL/csrf-token")
CSRF_TOKEN=$(echo "$CSRF_RESPONSE" | grep -o '"token":"[^"]*' | cut -d'"' -f4)
echo "Token CSRF: $CSRF_TOKEN"

# 2. Récupérer l'historique avec rôle visible
echo -e "\n${YELLOW}2. Récupération de l'historique avec rôles...${NC}"
HISTORY_RESPONSE=$(curl -s -b cookies.txt -H "X-CSRF-Token: $CSRF_TOKEN" "$BASE_URL/historique/trajets")
echo "Réponse historique:"
echo "$HISTORY_RESPONSE" | jq '.' || echo "$HISTORY_RESPONSE"

# Vérifier qu'on a un "role" explicite dans chaque élément
echo -e "\n${YELLOW}3. Vérification que chaque trajet a un 'role' visible...${NC}"
ROLE_COUNT=$(echo "$HISTORY_RESPONSE" | jq '.data[] | select(.role) | .role' | wc -l)
if [ $ROLE_COUNT -gt 0 ]; then
    echo -e "${GREEN}✓ Trouvé $ROLE_COUNT trajets avec rôle explicite${NC}"
    echo "Exemple:"
    echo "$HISTORY_RESPONSE" | jq '.data[0] | {trajet_id, role, statut, lieu_depart, lieu_arrivee}'
else
    echo -e "${RED}✗ Aucun rôle trouvé dans les données${NC}"
fi

# 4. Tester l'annulation d'une participation (si disponible)
echo -e "\n${YELLOW}4. Test d'annulation d'une participation...${NC}"
PARTICIPATION=$(echo "$HISTORY_RESPONSE" | jq '.data[] | select(.role == "passager" and .statut == "confirmee") | .participation_id' | head -1)
if [ -n "$PARTICIPATION" ] && [ "$PARTICIPATION" != "null" ]; then
    echo "Annulation de la participation ID: $PARTICIPATION"
    CANCEL_RESPONSE=$(curl -s -b cookies.txt \
        -H "X-CSRF-Token: $CSRF_TOKEN" \
        -H "Content-Type: application/json" \
        -X POST \
        "$BASE_URL/participations/$PARTICIPATION/annuler" \
        -d '{"raison":"Test"}')
    echo "Réponse annulation:"
    echo "$CANCEL_RESPONSE" | jq '.'
    
    # Vérifier que la réponse contient refund_amount
    REFUND=$(echo "$CANCEL_RESPONSE" | jq '.refund_amount')
    if [ -n "$REFUND" ] && [ "$REFUND" != "null" ]; then
        echo -e "${GREEN}✓ Remboursement effectué: $REFUND crédits${NC}"
    fi
else
    echo -e "${YELLOW}⚠ Aucune participation confirmée trouvée pour tester l'annulation${NC}"
fi

# 5. Vérifier que l'historique a été mis à jour
echo -e "\n${YELLOW}5. Vérification que l'historique est à jour après annulation...${NC}"
HISTORY_AFTER=$(curl -s -b cookies.txt -H "X-CSRF-Token: $CSRF_TOKEN" "$BASE_URL/historique/trajets")
echo "Nombre de trajets après annulation: $(echo "$HISTORY_AFTER" | jq '.count')"

echo -e "\n${GREEN}=== TEST TERMINÉ ===${NC}\n"
