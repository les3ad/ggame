CREATE TABLE tiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    x INT NOT NULL,
    y INT NOT NULL,
    tile_image VARCHAR(255) NOT NULL
);

CREATE TABLE uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_name VARCHAR(255) NOT NULL
);
