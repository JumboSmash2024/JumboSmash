-- List of all user accounts
CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT NOT NULL,
    -- What comes before @tufts.edu in their email
    user_tufts_name VARCHAR(255) DEFAULT '' NOT NULL,
    user_personal_email VARCHAR(255) DEFAULT '' NOT NULL,
    user_pass_hash VARCHAR(255) DEFAULT '' NOT NULL,
    -- Account status, bit flag:
    -- 0 = none
    -- 1 = verified
    -- 2 = disabled
    -- 512 = management/superuser
    user_status INT UNSIGNED NOT NULL,

    PRIMARY KEY(user_id),
    -- Cannot use the same email multiple times
    UNIQUE INDEX user_tufts_name(user_tufts_name),
    UNIQUE INDEX user_personal_email(user_personal_email)
)
