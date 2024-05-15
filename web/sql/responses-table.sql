-- Responses
CREATE TABLE responses (
    -- Primary key for reference
    resp_id INT UNSIGNED AUTO_INCREMENT NOT NULL,
    -- User who submitted this response
    resp_user INT UNSIGNED NOT NULL,
    -- User about whom this response was submitted
    resp_target INT UNSIGNED NOT NULL,
    -- Value of the response, bit flag:
    -- 0 = PASS
    -- 1 = SMASH
    resp_value INT UNSIGNED NOT NULL,

    PRIMARY KEY(resp_id),
    -- Cannot respond multiple times
    UNIQUE INDEX resp_user_target(resp_user, resp_target)
)
