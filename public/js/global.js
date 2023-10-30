/**
 * Returns the name of a time zone matching the UTC string via moment.js
 *
 * @param tzDatabaseName as defined in the tz database (https://en.wikipedia.org/wiki/List_of_tz_database_time_zones)
 *
 * @return {string|*} a descriptive string describing the time zone and it's relativity to UTC time
 */
function getTimeZoneName(tzDatabaseName)
{
    for (let i = 0; i < timezones_global_data.length; i++) {
        let getTimeZoneName = timezones_global_data[i].utc.find(
            function (timeZoneId) {
                return timeZoneId === tzDatabaseName;
            }
        );

        if (getTimeZoneName) {
            return timezones_global_data[i].text;
        }
    }

    return "(UTC) Monrovia, Reykjavik"; // default to UTC if no matching time zone is found
}
