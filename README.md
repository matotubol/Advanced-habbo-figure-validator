
# FigureValidator

The `FigureValidator` is a robust PHP tool designed to validate avatar looks against a provided `FigureData.json`. This tool ensures that the inputted look string adheres to the specific rules and structures defined in the `FigureData.json`.

## 🌟 Features

- **JSON Structure Validation**: Before diving into specific validations, the tool first ensures that the provided JSON has the correct structure.

- **setTypes Validation**: The tool validates against the `setTypes` present in the JSON. This includes checking the validity of:
   * The setType itself.
   * The associated set items.
   * The colors related to a setType.

- **Mandatory setType Checks**: Based on gender and Habbo Club membership, the tool checks to ensure that all mandatory setTypes are present in the look string.

- **Color Validations**: For colorable items, the tool checks if the chosen colors are valid against the provided palettes in the JSON. It also verifies if the color requires a Habbo Club membership and if it matches the user's HC status.

- **Gender and Habbo Club Validations**: The tool ensures that the chosen set items are appropriate for the gender and the HC membership status of the avatar.

- **Logging**: Any inconsistencies or invalid components in the look string are logged, making it easy to pinpoint the exact nature of the error.

## 🔧 Usage

1. Load the `FigureData.json`:
```php
$jsonContent = file_get_contents('./gamedata/FigureData.json');
$data = json_decode($jsonContent, true);
```

2. Generate a valid look string:
```php
$randomValidString = generateValidLook('M', false, $indexedSetTypes, $indexedPalettes);
```

3. Validate a given look string:
```php
$gender = "M";
$hasHabboClub = false;
if (validateItemString($randomValidString, $gender, $indexedSetTypes, $indexedPalettes, $hasHabboClub)) {
    echo "Valid";
} else {
    echo "Invalid";
}
```

## 🤝 Contribution

If you'd like to contribute to the project or have found any issues, please open an issue or a pull request on this GitHub repository.
