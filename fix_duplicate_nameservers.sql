-- First, let's see what's causing the conflict
SELECT user_email, domain_id, COUNT(*) as count 
FROM domain_nameservers 
WHERE user_email IN ('gdelfava@gmail.com', 'webmaster@kaldera.co.za')
GROUP BY user_email, domain_id 
HAVING COUNT(*) > 1;

-- Check for domains that exist for both users
SELECT domain_id, 
       COUNT(DISTINCT user_email) as user_count,
       GROUP_CONCAT(DISTINCT user_email) as users
FROM domain_nameservers 
WHERE user_email IN ('gdelfava@gmail.com', 'webmaster@kaldera.co.za')
GROUP BY domain_id 
HAVING COUNT(DISTINCT user_email) > 1;

-- Solution 1: Delete the old records first, then update
-- This is safer if you want to keep the webmaster@kaldera.co.za records

-- Delete nameserver records for gdelfava@gmail.com where webmaster@kaldera.co.za already has the same domain_id
DELETE n1 FROM domain_nameservers n1
INNER JOIN domain_nameservers n2 ON n1.domain_id = n2.domain_id
WHERE n1.user_email = 'gdelfava@gmail.com' 
AND n2.user_email = 'webmaster@kaldera.co.za';

-- Now update the remaining records
UPDATE domain_nameservers 
SET user_email = 'webmaster@kaldera.co.za' 
WHERE user_email = 'gdelfava@gmail.com';

-- Solution 2: Alternative approach - Update with conflict resolution
-- If you want to merge the data instead of deleting

-- First, update nameservers for domains that don't have conflicts
UPDATE domain_nameservers 
SET user_email = 'webmaster@kaldera.co.za' 
WHERE user_email = 'gdelfava@gmail.com'
AND domain_id NOT IN (
    SELECT DISTINCT n1.domain_id 
    FROM domain_nameservers n1
    INNER JOIN domain_nameservers n2 ON n1.domain_id = n2.domain_id
    WHERE n1.user_email = 'gdelfava@gmail.com' 
    AND n2.user_email = 'webmaster@kaldera.co.za'
);

-- Then handle the conflicts by merging data (optional)
-- This would require more complex logic to merge the nameserver data 