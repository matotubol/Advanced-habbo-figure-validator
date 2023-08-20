<?php

$jsonContent = file_get_contents('./gamedata/FigureData.json');
$data = json_decode($jsonContent, true);

if (!$data || !isset($data['setTypes'], $data['palettes'])) {
    die("Error: Issue with JSON data.");
}

$indexedSetTypes = [];
$indexedPalettes = [];

// Index setTypes
foreach ($data['setTypes'] as $setType) {
    $indexedSets = [];
    foreach ($setType['sets'] as $set) {
        $indexedSets[$set['id']] = $set;
    }
    $setType['sets'] = $indexedSets;
    $indexedSetTypes[$setType['type']] = $setType;
}

// Index palettes
foreach ($data['palettes'] as $palette) {
    $indexedColors = [];
    foreach ($palette['colors'] as $color) {
        $indexedColors[$color['id']] = $color;
    }
    $palette['colors'] = $indexedColors;
    $indexedPalettes[$palette['id']] = $palette;
}

function logMessage($message) {
    echo $message . PHP_EOL;
}

function validateColor($colorIndex, $paletteId, $indexedPalettes, $hasHabboClub) {
    $palette = $indexedPalettes[$paletteId] ?? null;
    if (!$palette) {
        logMessage("Failed: palette not found for palette ID '$paletteId'\n");
        return false;
    }

    $color = $palette['colors'][$colorIndex] ?? null;
    if ($color && (!$color['club'] || ($color['club'] && $hasHabboClub))) {
        return true;
    }

    logMessage("Failed: color index '$colorIndex' is invalid or requires Habbo Club\n");
    return false;
}

function getValidColors($palette, $hasHabboClub) {
    return array_filter($palette['colors'], function($color) use ($hasHabboClub) {
        return !$color['club'] || ($color['club'] && $hasHabboClub);
    });
}

function generateNonHCItem($setTypeStr, $gender, $indexedSetTypes, $indexedPalettes) {
    $setType = $indexedSetTypes[$setTypeStr];
    $palette = $indexedPalettes[$setType['paletteId']];
    
    $validColors = getValidColors($palette, false);
    $firstValidColor = reset($validColors);

    foreach ($setType['sets'] as $set) {
        if (($set['gender'] === 'U' || $set['gender'] === $gender) && !$set['club']) {
            return "$setTypeStr-" . $set['id'] . "-" . $firstValidColor['id'];
        }
    }

    return null; // No valid non-HC set found for given setType and gender
}

function validateSingleItem($item, $gender, $indexedSetTypes, $indexedPalettes, $hasHabboClub) {    
    $splitItem = explode('-', $item);

    if (count($splitItem) > 4) {
        logMessage("Failed: Too many colors specified for item '$item'\n");
        return false;
    }

    $setTypeStr = $splitItem[0];
    $setId = (int) $splitItem[1];

    $setType = $indexedSetTypes[$setTypeStr] ?? null;
    if (!$setType) {
        logMessage("Failed: setType not found for '$setTypeStr'\n");
        return false;
    }

    $setItem = $setType['sets'][$setId] ?? null;
    if (!$setItem) {
        logMessage("Failed: setItem not found for ID '$setId'\n");
        return false;
    }

    if (!$hasHabboClub && $setItem['club']) {
        // Replace with non-HC set
        logMessage("Failed: setItem '$setId' is only for HC members\n");
        return generateNonHCItem($setTypeStr, $gender, $indexedSetTypes, $indexedPalettes);
    }

    if ($setItem['gender'] !== 'U' && $setItem['gender'] !== $gender) {
        logMessage("Failed: gender mismatch for item ID '$setId'\n");
        return false;
    }

    // Check if the set is colorable
    if ($setItem['colorable']) {
        for ($i = 2; $i < count($splitItem); $i++) {
            $colorIndex = (int) $splitItem[$i];
            if (!validateColor($colorIndex, $setType['paletteId'], $indexedPalettes, $hasHabboClub)) {
                // Replace with first non-HC color
                $palette = $indexedPalettes[$setType['paletteId']];
                $validColors = getValidColors($palette, false);
                $firstValidColor = reset($validColors);
                $splitItem[$i] = $firstValidColor['id'];
            }    
        }
    } else {
        // If the set isn't colorable, just return the setType-setId format
        return "$setTypeStr-$setId";
    }

    return implode("-", $splitItem);
}

function validateMandatorySetTypes($gender, $hasHabboClub, $indexedSetTypes, &$validatedItems, $indexedPalettes) {
    $existingSetTypes = array_map(function($item) {
        return explode('-', $item)[0]; // Extract setType from items
    }, $validatedItems);

    foreach ($indexedSetTypes as $setTypeStr => $setType) {
        $isMandatory = false;

        // Determine if the setType is mandatory based on gender and HC status
        switch ($gender) {
            case 'M':
                $isMandatory = $hasHabboClub ? $setType['mandatory_m_1'] : $setType['mandatory_m_0'];
                break;
            case 'F':
                $isMandatory = $hasHabboClub ? $setType['mandatory_f_1'] : $setType['mandatory_f_0'];
                break;
        }

        // If the setType is mandatory but hasn't been encountered, add the default non-HC item for it
        if ($isMandatory && !in_array($setTypeStr, $existingSetTypes)) {
            logMessage("Notice: Mandatory setType '$setTypeStr' is missing. Adding default.");
            $defaultItem = generateNonHCItem($setTypeStr, $gender, $indexedSetTypes, $indexedPalettes);
            if ($defaultItem) {
                $validatedItems[] = $defaultItem;
            } else {
                logMessage("Failed: Could not generate a default item for setType '$setTypeStr'");
                return false;
            }
        }
    }
    return true;
}



function validateItemString($itemString, $gender, $indexedSetTypes, $indexedPalettes, $hasHabboClub) {
    $items = explode('.', $itemString);
    $validatedItems = [];
    $encounteredSetTypes = []; //track encountered setTypes

    foreach ($items as $item) {
        $setType = strstr($item, '-', true); // Extract setType up to the first '-'

        // Check if setType has already been encountered
        if (isset($encounteredSetTypes[$setType])) {
            logMessage("Skipped: Duplicate setType '$setType'");
            continue; // skip this item and move to the next one
        }

        $encounteredSetTypes[$setType] = true;

        $validatedItem = validateSingleItem($item, $gender, $indexedSetTypes, $indexedPalettes, $hasHabboClub);
        if ($validatedItem) {
            $validatedItems[] = $validatedItem;
        }
    }

    // After checking all items, validate that all mandatory setTypes are encountered
    if (!validateMandatorySetTypes($gender, $hasHabboClub, $indexedSetTypes, $validatedItems, $indexedPalettes)) {
        return false;
    }

    return implode(".", $validatedItems);
}

/////GENERATOR/////

function getRandomValidSet($setType, $gender, $hasHabboClub) {
    $validSets = [];

    foreach ($setType['sets'] as $set) {
        if ($set['gender'] === 'U' || $set['gender'] === $gender) {
            if (!$set['club'] || ($set['club'] && $hasHabboClub)) {
                $validSets[] = $set;
            }
        }
    }

    if (empty($validSets)) {
        return null; // No valid sets found
    }

    return $validSets[array_rand($validSets)]; // Return a random valid set
}

function generateValidLook($gender, $hasHabboClub, $indexedSetTypes, $indexedPalettes) {
    $lookParts = [];

    foreach ($indexedSetTypes as $setTypeStr => $setType) {
        $isMandatory = false;
        
        switch ($gender) {
            case 'M':
                $isMandatory = $hasHabboClub ? $setType['mandatory_m_1'] : $setType['mandatory_m_0'];
                break;
            case 'F':
                $isMandatory = $hasHabboClub ? $setType['mandatory_f_1'] : $setType['mandatory_f_0'];
                break;
        }

        // Include setType if it's mandatory or with a 50% chance for non-mandatory setTypes
        if ($isMandatory || mt_rand(0, 1)) { 
            $randomSet = getRandomValidSet($setType, $gender, $hasHabboClub);
            if ($randomSet) {
                $lookPart = "{$setTypeStr}-{$randomSet['id']}";
                
                // If set is colorable, add a random valid color
                if ($randomSet['colorable']) {
                    $palette = $indexedPalettes[$setType['paletteId']];
                    $validColors = getValidColors($palette, $hasHabboClub);
                    $randomColor = $validColors[array_rand($validColors)];
                    $lookPart .= "-{$randomColor['id']}";
                } 
                
                $lookParts[] = $lookPart;
            }
        }
    }

    $lookString = implode('.', $lookParts);

    // Ensure the generated look is valid
    if (validateItemString($lookString, $gender, $indexedSetTypes, $indexedPalettes, $hasHabboClub)) {
        return $lookString;
    }

    // If the look is not valid, generate new look from only the mandatories first item with first color.
    return generateDefaultLook($gender, $indexedSetTypes, $indexedPalettes);
}

function generateDefaultLook($gender, $indexedSetTypes, $indexedPalettes) {
    $defaultLook = [];

    foreach ($indexedSetTypes as $setTypeStr => $setType) {
        $isMandatory = false;

        // Determine if the setType is mandatory based on gender
        switch ($gender) {
            case 'M':
                $isMandatory = $setType['mandatory_m_0']; // Assuming non-HC as default
                break;
            case 'F':
                $isMandatory = $setType['mandatory_f_0']; // Assuming non-HC as default
                break;
        }

        if ($isMandatory) {
            $palette = $indexedPalettes[$setType['paletteId']];
            $validColors = getValidColors($palette, false); // non-HC as default

            if (!empty($validColors)) {
                $firstColor = reset($validColors);
                $colorId = $firstColor['id'];

                // Find the first set for this setType that matches the gender
                foreach ($setType['sets'] as $setId => $set) {
                    if ($set['gender'] === 'U' || $set['gender'] === $gender) {
                        // Add this set to the default look
                        if ($set['colorable']) {
                            $defaultLook[] = "$setTypeStr-$setId-$colorId";
                        } else {
                            $defaultLook[] = "$setTypeStr-$setId";
                        }
                        break;
                    }
                }
            }
        }
    }

    return implode('.', $defaultLook);
}

// Example usages:
$gender = 'M'; // or 'F'
$defaultLookForMale = generateDefaultLook($gender, $indexedSetTypes, $indexedPalettes);
$gen = generateValidLook($gender, true, $indexedSetTypes, $indexedPalettes);

$randomValidString = generateValidLook('M', true, $indexedSetTypes, $indexedPalettes);

$gender = "M";
$hasHabboClub = false; //so you can see how it updates the look when user cant wear that item or color
echo 'Random generated valid look '.$gen.'<br>';
echo validateItemString($gen, $gender, $indexedSetTypes, $indexedPalettes, $hasHabboClub);

?>
