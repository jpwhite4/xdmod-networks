UPDATE
    ${DESTINATION_SCHEMA}.transferfact t, ${DESTINATION_SCHEMA}.users u, ${UTILITY_SCHEMA}.systemaccount sa
SET
    t.person_id = sa.person_id
WHERE
    t.user_id = u.id AND sa.username = u.username AND t.person_id = -1
//
